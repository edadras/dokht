FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends blender python3 ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY render-engine /engine
RUN chmod +x /engine/worker.py

CMD ["python3", "/engine/worker.py"]

