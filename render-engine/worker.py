#!/usr/bin/env python3
import glob, os, shutil, subprocess, time

QUEUE = '/data/app/render-queue'
PROCESSING = '/data/app/render-processing'
FAILED = '/data/app/render-failed'

for directory in (QUEUE, PROCESSING, FAILED, '/data/app/public/renders'):
    os.makedirs(directory, exist_ok=True)

while True:
    jobs = sorted(glob.glob(os.path.join(QUEUE, '*.json')))
    if not jobs:
        time.sleep(2)
        continue
    source = jobs[0]
    job = os.path.join(PROCESSING, os.path.basename(source))
    try:
        os.replace(source, job)
        sewn = job + '.sewn.json'
        subprocess.run([
            'node', '/engine/sew.mjs', job, sewn,
        ], check=True, timeout=900)
        subprocess.run([
            'blender', '--background', '--factory-startup',
            '--python', '/engine/render.py', '--', job, sewn,
        ], check=True, timeout=900)
        os.remove(job)
        os.remove(sewn)
    except Exception as error:
        failure = os.path.join(FAILED, os.path.basename(job))
        try:
            shutil.move(job, failure)
            with open(failure + '.error', 'w', encoding='utf-8') as stream:
                stream.write(str(error))
        except OSError:
            pass
        time.sleep(2)

