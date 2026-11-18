"""Use the pinned CRM's closed backup contract in the native CI fixture.

Imports have no database/process side effects. Source, security, catalog and
rows belong to one exported snapshot, whose lifecycle is owned by the CRM.
"""
from __future__ import annotations

import importlib.util
from pathlib import Path
import re
import sys


MODULES = ("private_crm_backup_catalog", "private_crm_backup_contract", "private_crm_backup_snapshot")


def need(condition, reason):
    if not condition:
        raise RuntimeError(reason)


def load_crm_backup(source: Path):
    """Bind all three canonical modules to one exact archived source directory."""
    need(source.is_absolute() and source.is_dir() and not source.is_symlink(),
         "FEEDBACK_BACKUP_SOURCE_DIRECTORY")
    source = source.resolve()
    ops = source / "scripts/ops"
    need(ops.is_dir() and ops.resolve() == ops, "FEEDBACK_BACKUP_SOURCE_DIRECTORY")
    loaded, added = {}, []
    try:
        for name in MODULES:
            path = ops / (name + ".py")
            need(path.is_file() and not path.is_symlink() and path.resolve() == path,
                 "FEEDBACK_BACKUP_MODULE_SOURCE")
            module = sys.modules.get(name)
            if module is not None:
                origin = getattr(module, "__file__", None)
                need(isinstance(origin, str) and Path(origin).resolve() == path,
                     "FEEDBACK_BACKUP_MODULE_ALREADY_LOADED_ELSEWHERE")
            else:
                spec = importlib.util.spec_from_file_location(name, path)
                need(spec is not None and spec.loader is not None, "FEEDBACK_BACKUP_MODULE_SPEC")
                module = importlib.util.module_from_spec(spec)
                sys.modules[name] = module
                added.append(name)
                spec.loader.exec_module(module)
            loaded[name] = module
        catalog, contract, snapshot = (loaded[name] for name in MODULES)
        need(contract.catalog_manifest is catalog.catalog_manifest and snapshot.require is contract.require,
             "FEEDBACK_BACKUP_MODULE_BINDING")
        return contract, snapshot
    except BaseException:
        for name in reversed(added):
            sys.modules.pop(name, None)
        raise


def capture_crm_backup(backup, snapshot_module, container: str, base_tables: list[str], table_snapshot):
    """Capture the monitor profile without substituting independent live reads."""
    need(isinstance(container, str) and re.fullmatch(r"[a-f0-9]{64}", container),
         "FEEDBACK_BACKUP_CONTAINER_SCOPE")
    # CrmLab records the full ID returned by Docker for its owned source cluster.
    # The fixture listens on 5433; all keeper, control, query and dump connections
    # inherit the same port. pg_dump receives the CRM exporter's snapshot ID.
    command = ["docker", "exec", "-i", "-e", "PGPORT=5433", container]
    with snapshot_module.exported_snapshot(command) as snapshot:
        query = snapshot.query
        profile, security = backup.detect_profile(query, base_tables)
        need(profile.get("name") == "synthetic-private-s2s-execution-monitor-v1"
             and profile.get("execution_monitor") is True, "FEEDBACK_BACKUP_PROFILE")
        catalog = backup.catalog_manifest(query, schemas=("public", "crm_auto_service"))
        rows = table_snapshot(query, profile["tables"])
        contents = snapshot.dump()
        need(isinstance(contents, bytes) and contents.startswith(b"PGDMP")
             and len(contents) < 64 * 1024 * 1024, "FEEDBACK_CRM_DUMP_FORMAT")
        exported_at = snapshot.exported_at
    return {"profile": profile, "security": security, "catalog": catalog, "rows": rows,
            "contents": contents, "snapshot_exported_at_utc": exported_at}
