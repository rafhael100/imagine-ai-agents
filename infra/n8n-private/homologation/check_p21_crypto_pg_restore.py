#!/usr/bin/env python3
"""CENTRAL-AUTO-P21: prove P20 CMS backup restores REAL synthetic PG dumps.

The three databases contain synthetic rows only. All containers run in
GitHub Actions, not Leaseweb. The backup is encrypted, copied to a second
RUNNER-LOCAL directory, verified, extracted and restored into NEW temporary
PostgreSQL databases. It is not an external backup or production DR proof.
"""
from __future__ import annotations

import hashlib
import io
import json
import os
import shutil
import stat
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path

from material_preflight import fingerprint
from seal_backup import COMPONENTS, Denied, seal, verify_decrypted

REPO="rafhael100/imagine-ai-agents"
TABLE="p21_synthetic_records"
DBS=(
    ("n8n-pg.dump", "PG17_CONTAINER",
     "p21_n8n_source_ci", "p21_n8n_restored_ci", "SYNTHETIC_N8N_WORKFLOW"),
    ("crm-service.dump", "PG16_CONTAINER",
     "p21_crm_source_ci", "p21_crm_restored_ci", "SYNTHETIC_CRM_EVENT"),
    ("agents-service.dump", "PG16_CONTAINER",
     "p21_agents_source_ci", "p21_agents_restored_ci", "SYNTHETIC_AGENTS_EVENT"),
)


class Failure(Exception):
    pass


def require(ok: bool,label: str)->None:
    if not ok:
        raise Failure(label)


def safe_run(args: list[str], *, stdin:bytes|None=None,
             timeout:int=35)->bytes:
    proc=subprocess.run(args,input=stdin,stdout=subprocess.PIPE,
                        stderr=subprocess.PIPE,check=False,timeout=timeout)
    if proc.returncode:
        # Do not emit database contents/keys in logs.
        raise Failure("COMMAND_DENIED_"+args[0]+"_EXIT_"+str(proc.returncode))
    return proc.stdout


def pg(container:str,*args:str,stdin:bytes|None=None)->bytes:
    require(bool(container) and len(container)>8 and all(
      c in "0123456789abcdef" for c in container.lower()
    ),"POSTGRES_CONTAINER_ID_NOT_PINNED_TO_GITHUB_JOB")
    return safe_run(["docker","exec","-i",container,*args],stdin=stdin)


def sha256(data:bytes)->str:
    return hashlib.sha256(data).hexdigest()


def pg_scalar(container:str,dbname:str,query:str)->str:
    result=pg(container,"psql","-X","-v","ON_ERROR_STOP=1",
              "-qAt","-U","postgres","-d",dbname,"-c",query)
    return result.decode("ascii").strip()


def issue_database(container:str,dbname:str,event:str)->str:
    # These names and event refs are constants, never user-supplied SQL.
    pg(container,"createdb","-U","postgres",dbname)
    sql=(
       f"CREATE TABLE {TABLE}("
       "event_ref text PRIMARY KEY NOT NULL,"
       "tenant_ref text NOT NULL,"
       "ai_tokens integer NOT NULL CHECK (ai_tokens=0),"
       "credits_debited integer NOT NULL CHECK (credits_debited=0));"
       f"INSERT INTO {TABLE}(event_ref,tenant_ref,ai_tokens,credits_debited)"
       f" VALUES ('{event}','SYNTHETIC_TENANT_CI',0,0);"
    )
    pg(container,"psql","-X","-v","ON_ERROR_STOP=1",
       "-U","postgres","-d",dbname,"-c",sql)
    return pg_scalar(container,dbname,
        f"SELECT event_ref||'|'||tenant_ref||'|'||ai_tokens::text"
        f"||'|'||credits_debited::text FROM {TABLE}")


def real_dump(container:str,db:str)->bytes:
    raw=pg(container,"pg_dump","-U","postgres","-d",db,"-Fc",
           "--no-owner","--no-acl")
    require(len(raw)>100 and raw[:5]==b"PGDMP","PG_DUMP_REAL_FORMAT_REQUIRED")
    listing=pg(container,"pg_restore","--list",stdin=raw).decode(
        "utf-8","replace")
    require(TABLE in listing,"PG_DUMP_MUST_CONTAIN_TABLE")
    return raw


def restore(container:str,db:str,dump:bytes)->str:
    require(dump[:5]==b"PGDMP","INVALID_PG_DUMP_HEADER")
    pg(container,"createdb","-U","postgres",db)
    pg(container,"pg_restore","--exit-on-error","--no-owner","--no-acl",
       "-U","postgres","-d",db,stdin=dump)
    return pg_scalar(container,db,
        f"SELECT event_ref||'|'||tenant_ref||'|'||ai_tokens::text"
        f"||'|'||credits_debited::text FROM {TABLE}")


def make_volume(path:Path)->bytes:
    payload=b'CENTRAL_AUTO_P21_SYNTHETIC_WORKFLOW_NO_AI_TRUE\n'
    with tarfile.open(path,mode="w") as archive:
        item=tarfile.TarInfo("workflows/native-funnel.json")
        item.size=len(payload);item.mode=0o600
        archive.addfile(item,io.BytesIO(payload))
    path.chmod(0o600)
    return payload


def make_cert(directory:Path,name:str)->tuple[Path,Path]:
    cert=directory/(name+".crt")
    key=directory/(name+".key")
    safe_run(["openssl","req","-x509","-newkey","rsa:2048",
       "-nodes","-days","3","-sha256",
       "-subj","/CN=CENTRAL_AUTO_P21_SYNTHETIC_"+name,
       "-addext","extendedKeyUsage=emailProtection",
       "-keyout",str(key),"-out",str(cert)])
    cert.chmod(0o600);key.chmod(0o600)
    return cert,key


def decrypt(enc:Path,cert:Path,key:Path,dest:Path)->bool:
    proc=subprocess.run(["openssl","cms","-decrypt","-binary",
         "-inform","DER","-in",str(enc),"-recip",str(cert),
         "-inkey",str(key),"-out",str(dest)],
         stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=35)
    return proc.returncode==0


def restored_volume_content(tarpath:Path,output:Path)->bytes:
    # verify_decrypted has already checked the outer tar manifest.
    with tarfile.open(tarpath,mode="r:") as archive:
        member=archive.getmember("n8n-data.tar")
        with archive.extractfile(member) as f:
            data=f.read()
    recovered_tar=output/"restored-volume.tar"
    recovered_tar.write_bytes(data);recovered_tar.chmod(0o600)
    with tarfile.open(recovered_tar,"r:") as archive:
        member=archive.getmember("workflows/native-funnel.json")
        require(member.isfile() and len(archive.getmembers())==1,
                "VOLUME_TAR_MISMATCH")
        with archive.extractfile(member) as reader:
            return reader.read()


def main()->dict:
    require(os.environ.get("GITHUB_ACTIONS")=="true" and
            os.environ.get("GITHUB_REPOSITORY")==REPO,
            "GITHUB_CI_ONLY")
    containers={key:os.environ.get(key,"") for key in (
        "PG17_CONTAINER","PG16_CONTAINER")}
    require(all(containers.values()) and
            containers["PG17_CONTAINER"]!=containers["PG16_CONTAINER"],
            "SEPARATE_PG16_AND_PG17_SERVICES_REQUIRED")
    require(set(COMPONENTS)=={"n8n-pg.dump","n8n-data.tar",
              "n8n-encryption.key","crm-service.dump","agents-service.dump"},
            "P20_PACKAGE_SCHEMA_CHANGED")

    with tempfile.TemporaryDirectory(prefix="central-auto-p21-") as tmp:
        root=Path(tmp)
        snapshot=root/"snapshot"
        snapshot.mkdir(mode=0o700)
        quarantine=root/"quarantine"
        quarantine.mkdir(mode=0o700)
        external_sim=root/"separate-runner-directory"
        external_sim.mkdir(mode=0o700)
        original_rows={}
        for filename,env,source,recovered,event in DBS:
            container=containers[env]
            require(pg_scalar(container,"postgres","SHOW server_version_num").startswith(
                "17" if env=="PG17_CONTAINER" else "16"),
                "WRONG_POSTGRES_MAJOR_"+env)
            original_rows[filename]=issue_database(container,source,event)
            dump=real_dump(container,source)
            path=snapshot/filename
            path.write_bytes(dump);path.chmod(0o600)

        volume_payload=make_volume(snapshot/"n8n-data.tar")
        key_bytes=os.urandom(32).hex().encode("ascii")
        keyfile=snapshot/"n8n-encryption.key"
        keyfile.write_bytes(key_bytes);keyfile.chmod(0o600)

        cert,key=make_cert(root,"recipient")
        wrong_cert,wrong_key=make_cert(root,"wrongrecipient")
        outfile=root/"sealed-backup.cms"
        print("P21_STAGE_SEAL_BEGIN",flush=True)
        result=seal(snapshot,cert,fingerprint(cert),outfile)
        print("P21_STAGE_SEAL_PASS",flush=True)
        require(result["encrypted"] is True and
                result["go_for_installation"] is False and
                result["external_backup_verified"] is False,
                "FALSE_BACKUP_GO")
        require(outfile.stat().st_mode&0o777==0o600,"CIPHERTEXT_PERMISSIONS")

        copy=external_sim/"untrusted-copy.cms"
        shutil.copy2(outfile,copy)
        require(sha256(copy.read_bytes())==result["ciphertext_sha256"],
                "COPY_SHA_MISMATCH")
        negative_wrong=not decrypt(copy,wrong_cert,wrong_key,
                                   quarantine/"wrong-key.tar")
        require(negative_wrong,"WRONG_DECRYPTION_KEY_MUST_DENY")
        changed=bytearray(copy.read_bytes())
        require(len(changed)>80,"CMS_CIPHERTEXT_TOO_SMALL")
        changed[-20]^=1
        altered=external_sim/"altered-copy.cms"
        altered.write_bytes(changed);altered.chmod(0o600)
        negative_tamper=not decrypt(altered,cert,key,
                                   quarantine/"altered.tar")
        require(negative_tamper,"GCM_TAMPER_MUST_DENY")
        print("P21_STAGE_NEGATIVE_CMS_PASS",flush=True)
        clear=quarantine/"verified-package.tar"
        require(decrypt(copy,cert,key,clear),"CMS_AUTH_DECRYPT_FAILED")
        print("P21_STAGE_MANIFEST_BEGIN",flush=True)
        proof=verify_decrypted(clear)
        print("P21_STAGE_MANIFEST_PASS",flush=True)
        require(proof["component_hashes_match"] is True and
                proof["go_for_installation"] is False,
                "PACKAGE_VERIFICATION_FALSE_GO")

        with tarfile.open(clear,mode="r:") as archive:
            retrieved={}
            for member in archive:
                if member.name in COMPONENTS:
                    with archive.extractfile(member) as reader:
                        retrieved[member.name]=reader.read()
        require(set(retrieved)==set(COMPONENTS),
                "RESTORE_COMPONENTS_MISSING")
        require(retrieved["n8n-encryption.key"]==key_bytes,
                "ENCRYPTION_KEY_NOT_RECOVERED")
        require(restored_volume_content(clear,quarantine)==volume_payload,
                "VOLUME_DATA_NOT_RECOVERED")

        print("P21_STAGE_PG_RESTORE_BEGIN",flush=True)
        rows={}
        for filename,env,source,recovered,event in DBS:
            rows[filename]=restore(containers[env],recovered,
                                   retrieved[filename])
            require(rows[filename]==original_rows[filename] and
                    rows[filename].startswith(event+"|") and
                    rows[filename].endswith("|0|0"),
                    "PG_RESTORE_DATA_OR_NO_AI_FAILED_"+filename)
        return {
           "schema":"CENTRAL_AUTO_P21_CRYPTO_PG_RESTORE_V1",
           "pg17_real_dumps_restored":1,
           "pg16_real_dumps_restored":2,
           "synthetic_volume_restored":True,
           "synthetic_key_restored":True,
           "cms_gcm_copy_hash_match":True,
           "wrong_decryption_key_denied":negative_wrong,
           "altered_ciphertext_denied":negative_tamper,
           "no_ai_credits":True,
           "external_backup_verified":False,
           "different_host_restore_verified":False,
           "real_customer_credentials_restored":False,
           "vps_modified":False,
           "go_for_installation":False,
        }


if __name__=="__main__":
    try:
        print(json.dumps(main(),sort_keys=True))
        print("CENTRAL_AUTO_P21_CMS_PG_RESTORE_PASS NO_GO no_vps=1 no_paid_ai=1")
    except (Failure,Denied,OSError,ValueError,tarfile.TarError,
            subprocess.SubprocessError,AssertionError) as exc:
        # Important: no command stdout/stderr, cryptographic material,
        # database rows, names or secrets in public CI logs.
        print("CENTRAL_AUTO_P21_DENIED_"+type(exc).__name__,file=sys.stderr)
        raise SystemExit(1)
