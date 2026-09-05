FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
# Blender رسمیِ ۴٫۲ (نسخهٔ apt ۴٫۰ است و صدها بستهٔ گرافیکی می‌آورد)؛ Cycles روی CPU بی نمایشگر کار می‌کند
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl xz-utils nodejs python3 \
        libx11-6 libxi6 libxxf86vm1 libxfixes3 libxrender1 libgl1 libsm6 libxkbcommon0 libegl1 \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sSL https://download.blender.org/release/Blender4.2/blender-4.2.3-linux-x64.tar.xz -o /tmp/blender.tar.xz \
    && mkdir -p /opt/blender && tar -xJf /tmp/blender.tar.xz -C /opt/blender --strip-components=1 \
    && rm /tmp/blender.tar.xz \
    && ln -s /opt/blender/blender /usr/local/bin/blender

COPY render-engine /engine
COPY package.json /app/package.json
COPY resources/js /app/resources/js
RUN chmod +x /engine/worker.py

ENV BLENDER_USER_CONFIG=/tmp/blender-config
CMD ["python3", "/engine/worker.py"]

