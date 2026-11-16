#!/usr/bin/env python3
"""Strictly local, static Imagine Agents demo serving — NO operational API.

No CRM, SSO, external model, filesystem writes or storage. The only served
document is the already-reviewed offline HTML demo; all other URLs deny.
Bind to all interfaces ONLY within the isolated rootless Docker container;
Compose publishes the dynamic host port exclusively on 127.0.0.1.
"""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import os

DEMO = Path("/srv/demo/index.html")
CSP = (
    "default-src 'none'; connect-src 'none'; img-src 'none'; "
    "font-src 'none'; style-src 'unsafe-inline'; "
    "script-src 'unsafe-inline'; form-action 'none'; "
    "object-src 'none'; base-uri 'none'"
)
HEALTH = b"SIMULATED_READY_NO_GO\n"


class Handler(BaseHTTPRequestHandler):
    server_version = "ImagineDemoLab"
    sys_version = ""

    def _reply(self, status: int, body: bytes, media: str) -> None:
        self.send_response(status)
        self.send_header("Content-Type", media)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "private, no-store, max-age=0")
        self.send_header("Content-Security-Policy", CSP)
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("X-Frame-Options", "DENY")
        self.send_header("Referrer-Policy", "no-referrer")
        self.send_header("Permissions-Policy", "camera=(), microphone=(), geolocation=()")
        self.send_header("Cross-Origin-Resource-Policy", "same-origin")
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(body)

    def do_GET(self) -> None:
        if self.path in ("/", "/index.html"):
            self._reply(200, DEMO.read_bytes(), "text/html; charset=utf-8")
        elif self.path == "/healthz":
            self._reply(200, HEALTH, "text/plain; charset=utf-8")
        else:
            self._reply(404, b"Not found\n", "text/plain; charset=utf-8")

    def do_HEAD(self) -> None:
        self.do_GET()

    def do_POST(self) -> None:
        self._reply(404, b"Not found\n", "text/plain; charset=utf-8")

    def do_PUT(self) -> None:
        self.do_POST()

    def do_PATCH(self) -> None:
        self.do_POST()

    def do_DELETE(self) -> None:
        self.do_POST()

    def do_OPTIONS(self) -> None:
        self.do_POST()

    def log_message(self, fmt: str, *args: object) -> None:
        # Do not persist any request path, query string or arbitrary header.
        pass


if __name__ == "__main__":
    if os.environ.get("AIA_DEMO_ONLY") != "synthetic-v1":
        raise SystemExit("DEMO_DISABLED")
    if not DEMO.is_file():
        raise SystemExit("DEMO_ARTIFACT_MISSING")
    with ThreadingHTTPServer(("0.0.0.0", 8080), Handler) as httpd:
        httpd.serve_forever()
