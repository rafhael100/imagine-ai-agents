FROM python:3.13-alpine@sha256:9ef1a599f863231c3d91e941389bfdc4a2d6070557243d54df1a4e57015b960e

# P3 laboratory ONLY: no package manager, no external API and no runtime writes.
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 AIA_DEMO_ONLY=synthetic-v1
WORKDIR /srv
COPY examples/p0-assistente-comercial-demo.html /srv/demo/index.html
COPY deploy/agent-demo/server.py /srv/server.py
USER 10001:10001
EXPOSE 8080
CMD ["python", "/srv/server.py"]
