FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends blender nodejs python3 python3-numpy ca-certificates libegl1 libgl1 \
    && rm -rf /var/lib/apt/lists/*

COPY render-engine /engine
COPY package.json /app/package.json
COPY resources/js /app/resources/js
RUN chmod +x /engine/worker.py

CMD ["python3", "/engine/worker.py"]

