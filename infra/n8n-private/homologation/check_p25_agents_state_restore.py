#!/usr/bin/env python3
"""Recover n8n and the actual Agents candidate state from one signed package."""
import json
import subprocess
import sys
import tarfile

from check_p24_full_n8n_restore import Denied, Failure, NativeDenied, Rejected, main
from p25_agents_recovery import AgentsRecovery


if __name__ == "__main__":
    try:
        print(json.dumps(main(AgentsRecovery), sort_keys=True))
        print("CENTRAL_AUTO_P25_AGENTS_STATE_RECOVERY_PASS NO_GO")
    except (Failure, Denied, Rejected, NativeDenied, OSError, ValueError, KeyError,
            tarfile.TarError, subprocess.SubprocessError) as error:
        code = str(error) if isinstance(error, Failure) else type(error).__name__
        print("CENTRAL_AUTO_P25_DENIED_" + code, file=sys.stderr)
        raise SystemExit(1)
