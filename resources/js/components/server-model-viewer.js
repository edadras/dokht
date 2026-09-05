import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';

/**
 * نمایش‌دهندهٔ سبک برای GLB آمادهٔ سرور.
 *
 * هیچ دوخت یا شبیه‌سازی در مرورگر انجام نمی‌شود؛ این جزء فقط مدل نهایی Blender
 * را مثل یک فایل محصول باز می‌کند و امکان چرخاندن/بزرگ‌نمایی می‌دهد.
 */
export default function serverModelViewer() {
    // Three.js objects must stay outside Alpine's reactive data. Alpine wraps every
    // object stored on `this` in a Proxy, while Three.js relies on non-configurable
    // matrix properties and rejects those proxies during WebGL rendering.
    const runtime = {
        renderer: null,
        scene: null,
        camera: null,
        controls: null,
        garment: null,
        frame: null,
        resizeObserver: null,
        home: null,
    };

    return {
        ready: false,
        failed: false,
        message: '',
        spin: true,
        currentUrl: null,

        async load(url) {
            if (! url || url === this.currentUrl) return;

            this.currentUrl = url;
            this.ready = false;
            this.failed = false;
            this.message = '';
            this.teardown();

            try {
                this.mount();
                const gltf = await new Promise((resolve, reject) => {
                    new GLTFLoader().load(url, resolve, undefined, reject);
                });

                if (url !== this.currentUrl) return;

                runtime.garment = gltf.scene;
                const sceneOnlyMeshes = [];
                runtime.garment.traverse((child) => {
                    if (! child.isMesh) return;

                    // The Blender studio floor is useful for the still renders but
                    // makes the GLB bounding box several times wider than the
                    // mannequin. Drop it in the interactive view so camera fitting
                    // uses the mannequin and sewn garment themselves.
                    if (/^plane(?:\.\d+)?$/i.test(child.name || '')) {
                        sceneOnlyMeshes.push(child);
                        return;
                    }

                    child.castShadow = true;
                    child.receiveShadow = true;
                    if (child.material) child.material.side = THREE.DoubleSide;
                });
                sceneOnlyMeshes.forEach((mesh) => mesh.removeFromParent());
                runtime.scene.add(runtime.garment);
                this.fit();
                this.ready = true;
                this.animate();
            } catch (error) {
                this.failed = true;
                this.message = 'مدل سه‌بعدی سرور باز نشد؛ تصاویر رندر همچنان در دسترس‌اند.';
                console.error(error);
            }
        },

        mount() {
            const canvas = this.$refs.canvas;
            runtime.renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: false });
            runtime.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
            runtime.renderer.outputColorSpace = THREE.SRGBColorSpace;
            runtime.renderer.toneMapping = THREE.ACESFilmicToneMapping;
            runtime.renderer.toneMappingExposure = 1.05;

            runtime.scene = new THREE.Scene();
            runtime.scene.background = new THREE.Color(0x171717);
            runtime.camera = new THREE.PerspectiveCamera(34, 1, 0.01, 1000);
            runtime.controls = new OrbitControls(runtime.camera, canvas);
            runtime.controls.enableDamping = true;
            runtime.controls.dampingFactor = 0.07;
            runtime.controls.minDistance = 0.2;
            runtime.controls.maxDistance = 30;

            runtime.scene.add(new THREE.HemisphereLight(0xffffff, 0x57534e, 2.2));
            const key = new THREE.DirectionalLight(0xffffff, 3.2);
            key.position.set(4, 6, 5);
            runtime.scene.add(key);
            const fill = new THREE.DirectionalLight(0xdbeafe, 1.5);
            fill.position.set(-4, 3, 2);
            runtime.scene.add(fill);
            const rim = new THREE.DirectionalLight(0xffedd5, 1.25);
            rim.position.set(1, 4, -5);
            runtime.scene.add(rim);

            runtime.resizeObserver = new ResizeObserver(() => this.resize());
            runtime.resizeObserver.observe(canvas.parentElement);
            this.resize();
        },

        fit() {
            const box = new THREE.Box3().setFromObject(runtime.garment);
            const sphere = box.getBoundingSphere(new THREE.Sphere());
            const radius = Math.max(sphere.radius, 0.1);
            const distance = radius / Math.sin(THREE.MathUtils.degToRad(runtime.camera.fov / 2));

            runtime.controls.target.copy(sphere.center);
            runtime.camera.position.set(
                sphere.center.x + distance * 0.18,
                sphere.center.y + radius * 0.04,
                sphere.center.z + distance * 1.12,
            );
            runtime.camera.near = Math.max(radius / 100, 0.01);
            runtime.camera.far = distance * 8;
            runtime.camera.updateProjectionMatrix();
            runtime.controls.update();
            runtime.home = {
                position: runtime.camera.position.clone(),
                target: runtime.controls.target.clone(),
            };
        },

        resize() {
            if (! runtime.renderer || ! runtime.camera) return;
            const host = this.$refs.canvas.parentElement;
            const width = host.clientWidth;
            const height = host.clientHeight;
            if (! width || ! height) return;

            runtime.renderer.setSize(width, height, false);
            runtime.camera.aspect = width / height;
            runtime.camera.updateProjectionMatrix();
        },

        animate() {
            if (! runtime.renderer || runtime.frame) return;

            const draw = () => {
                if (! runtime.renderer) return;
                runtime.frame = requestAnimationFrame(draw);
                if (this.spin && runtime.garment) runtime.garment.rotation.y += 0.0035;
                runtime.controls?.update();
                runtime.renderer.render(runtime.scene, runtime.camera);
            };
            draw();
        },

        toggleSpin() {
            this.spin = ! this.spin;
        },

        recentre() {
            if (! runtime.home || ! runtime.camera || ! runtime.controls) return;
            runtime.camera.position.copy(runtime.home.position);
            runtime.controls.target.copy(runtime.home.target);
            if (runtime.garment) runtime.garment.rotation.y = 0;
            runtime.controls.update();
        },

        teardown() {
            if (runtime.frame) cancelAnimationFrame(runtime.frame);
            runtime.frame = null;
            runtime.resizeObserver?.disconnect();
            runtime.resizeObserver = null;
            runtime.controls?.dispose();
            runtime.controls = null;

            runtime.scene?.traverse((child) => {
                if (! child.isMesh) return;
                child.geometry?.dispose();
                const materials = Array.isArray(child.material) ? child.material : [child.material];
                materials.filter(Boolean).forEach((material) => material.dispose?.());
            });
            runtime.renderer?.dispose();
            runtime.renderer = null;
            runtime.scene = null;
            runtime.camera = null;
            runtime.garment = null;
            runtime.home = null;
        },

        destroy() {
            this.currentUrl = null;
            this.teardown();
        },
    };
}
