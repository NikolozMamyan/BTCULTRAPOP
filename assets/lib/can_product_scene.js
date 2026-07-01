import * as THREE from 'three';

const SHAPE_DEFAULTS = {
    widthScale: 1.08,
    height: 4.08,
    bodyBulge: 0.995,
    shoulder: 1.012,
    topCut: 0.82,
    topNeck: 0.8,
    bottomNeck: 0.81,
    lidScale: 1,
};
const MAX_PIXEL_RATIO = 3;
const FLAT_FRONT_TYPES = ['chip_bag', 'candy_bag', 'candy_stick_bag', 'cereal_box'];

export default class ProductModelScene {
    constructor(canvas, imageUrl, shape = {}, modelType = 'can') {
        this.canvas = canvas;
        this.imageUrl = imageUrl;
        this.modelType = this.normalizeModelType(modelType);
        this.shape = { ...SHAPE_DEFAULTS, ...shape };
        this.abortController = new AbortController();
        this.pointer = {
            active: false,
            x: 0,
            y: 0,
            velocityX: 0,
            velocityY: 0,
            lastMove: 0,
        };
        this.targetRotation = new THREE.Vector2(-0.06, this.preferredRotationY());
        this.currentRotation = this.targetRotation.clone();
        this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.cameraDistance = 6.4;
        this.canBottomY = -this.shape.height / 2;
        this.sceneTargetY = 0;
        this.animationFrame = 0;
        this.materials = null;
        this.texture = null;
    }

    async start() {
        if (!this.imageUrl) {
            throw new Error('Missing product texture.');
        }

        this.createRenderer();
        this.createScene();
        this.bindInput();
        this.resize();
        this.texture = await this.loadTexture(this.imageUrl);
        this.materials = this.createMaterials(this.texture);
        this.rebuildModel();
        this.animate();
    }

    dispose() {
        this.abortController.abort();
        window.cancelAnimationFrame(this.animationFrame);

        if (this.canModel) {
            this.clearGroup(this.canModel);
        }

        if (this.stageFloor?.geometry) {
            this.stageFloor.geometry.dispose();
        }

        Object.values(this.materials || {}).forEach((material) => material.dispose?.());
        this.texture?.dispose?.();
        this.renderer?.dispose?.();
    }

    updateShape(shape = {}, modelType = this.modelType) {
        const previousType = this.modelType;
        this.modelType = this.normalizeModelType(modelType);
        this.shape = { ...this.shape, ...shape };

        if (!this.materials) {
            return;
        }

        if (previousType !== this.modelType) {
            this.targetRotation.y = this.preferredRotationY();
            this.currentRotation.y = this.targetRotation.y;
        }

        this.rebuildModel();
        this.resize();
    }

    createRenderer() {
        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance',
        });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, MAX_PIXEL_RATIO));
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.08;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    }

    createScene() {
        this.scene = new THREE.Scene();
        this.scene.background = null;
        this.scene.fog = new THREE.Fog(0x090b22, 7, 16);

        this.camera = new THREE.PerspectiveCamera(35, 1, 0.1, 100);
        this.scene.add(this.camera);

        this.canGroup = new THREE.Group();
        this.canGroup.rotation.set(this.currentRotation.x, this.currentRotation.y, 0);
        this.scene.add(this.canGroup);

        this.canModel = new THREE.Group();
        this.canGroup.add(this.canModel);

        this.buildLighting();
        this.buildStage();
    }

    buildLighting() {
        this.scene.add(new THREE.HemisphereLight(0xfff5df, 0x1b1714, 2.25));

        const key = new THREE.DirectionalLight(0xffffff, 4.4);
        key.position.set(-3.2, 4.8, 5.2);
        key.castShadow = true;
        key.shadow.mapSize.set(2048, 2048);
        key.shadow.camera.near = 0.5;
        key.shadow.camera.far = 14;
        key.shadow.camera.left = -5;
        key.shadow.camera.right = 5;
        key.shadow.camera.top = 5;
        key.shadow.camera.bottom = -5;
        this.scene.add(key);

        const rim = new THREE.DirectionalLight(0xffcf7a, 1.4);
        rim.position.set(4.5, 2.6, -4.8);
        this.scene.add(rim);
    }

    buildStage() {
        this.stageFloor = new THREE.Mesh(
            new THREE.PlaneGeometry(20, 20),
            new THREE.ShadowMaterial({ color: 0x000000, opacity: 0.32 }),
        );
        this.stageFloor.rotation.x = -Math.PI / 2;
        this.stageFloor.receiveShadow = true;
        this.scene.add(this.stageFloor);
    }

    loadTexture(url) {
        return new Promise((resolve, reject) => {
            new THREE.TextureLoader().load(
                url,
                (texture) => {
                    texture.colorSpace = THREE.SRGBColorSpace;
                    texture.anisotropy = this.renderer.capabilities.getMaxAnisotropy();
                    texture.wrapS = THREE.RepeatWrapping;
                    texture.wrapT = THREE.ClampToEdgeWrapping;
                    resolve(texture);
                },
                undefined,
                () => reject(new Error('Unable to load product texture.')),
            );
        });
    }

    createMaterials(texture) {
        return {
            label: new THREE.MeshPhysicalMaterial({
                map: texture,
                roughness: 0.42,
                metalness: 0.08,
                clearcoat: 0.32,
                clearcoatRoughness: 0.22,
                side: THREE.DoubleSide,
            }),
            softLabel: new THREE.MeshPhysicalMaterial({
                map: texture,
                roughness: 0.54,
                metalness: 0.02,
                clearcoat: 0.18,
                clearcoatRoughness: 0.34,
                side: THREE.DoubleSide,
            }),
            plastic: new THREE.MeshPhysicalMaterial({
                color: 0xdfeeff,
                roughness: 0.18,
                metalness: 0.02,
                transmission: 0.16,
                thickness: 0.45,
                clearcoat: 0.42,
                clearcoatRoughness: 0.2,
                transparent: true,
                opacity: 0.68,
            }),
            cap: new THREE.MeshPhysicalMaterial({
                color: 0xd8d0bf,
                roughness: 0.28,
                metalness: 0.84,
                clearcoat: 0.42,
                clearcoatRoughness: 0.16,
            }),
            coloredCap: new THREE.MeshPhysicalMaterial({
                color: 0xff342c,
                roughness: 0.34,
                metalness: 0.12,
                clearcoat: 0.38,
                clearcoatRoughness: 0.18,
            }),
            pouchBack: new THREE.MeshStandardMaterial({
                color: 0xe8dfcf,
                roughness: 0.72,
                metalness: 0.02,
                side: THREE.DoubleSide,
            }),
            pouchSeal: new THREE.MeshStandardMaterial({
                color: 0xf5ecd8,
                roughness: 0.64,
                metalness: 0.04,
            }),
            boxSide: new THREE.MeshStandardMaterial({
                color: 0xf1e6cf,
                roughness: 0.56,
                metalness: 0.02,
            }),
            boxBack: new THREE.MeshStandardMaterial({
                color: 0xd7cbb6,
                roughness: 0.62,
                metalness: 0.02,
            }),
            cupInside: new THREE.MeshStandardMaterial({
                color: 0xfff9ef,
                roughness: 0.5,
                metalness: 0.03,
            }),
            edge: new THREE.LineBasicMaterial({
                color: 0xffffff,
                transparent: true,
                opacity: 0.22,
            }),
            rim: new THREE.MeshPhysicalMaterial({
                color: 0x292621,
                roughness: 0.22,
                metalness: 0.9,
                clearcoat: 0.35,
                clearcoatRoughness: 0.18,
            }),
            groove: new THREE.MeshStandardMaterial({
                color: 0x5d5448,
                roughness: 0.38,
                metalness: 0.72,
            }),
            black: new THREE.MeshBasicMaterial({
                color: 0x010101,
                side: THREE.DoubleSide,
            }),
            tabShadow: new THREE.MeshBasicMaterial({
                color: 0x1c1915,
                transparent: true,
                opacity: 0.26,
            }),
        };
    }

    rebuildModel() {
        switch (this.modelType) {
            case 'bottle':
                this.rebuildBottle();
                break;
            case 'chip_bag':
                this.rebuildPouch({ slim: false, candy: false });
                break;
            case 'noodle_cup':
                this.rebuildNoodleCup();
                break;
            case 'candy_bag':
                this.rebuildPouch({ slim: false, candy: true });
                break;
            case 'candy_stick_bag':
                this.rebuildPouch({ slim: true, candy: true });
                break;
            case 'cereal_box':
                this.rebuildCerealBox();
                break;
            case 'can':
            default:
                this.rebuildCan();
                break;
        }
    }

    rebuildCan() {
        this.clearGroup(this.canModel);

        const { height, radius, topY, bottomY } = this.getCanDimensions();
        const body = new THREE.Mesh(this.createCanBodyGeometry(height, radius, 224), this.materials.label);
        body.castShadow = true;
        body.receiveShadow = true;
        this.canModel.add(body);

        this.addCapDetails(radius, topY, bottomY);
        this.canBottomY = bottomY;
        this.syncStageFloor();
        this.refreshCameraDistance();
    }

    rebuildBottle() {
        this.clearGroup(this.canModel);

        const height = this.shape.height;
        const half = height / 2;
        const textureAspect = this.texture.image.width / this.texture.image.height;
        const radius = THREE.MathUtils.clamp(
            ((textureAspect * height) / (Math.PI * 3.25)) * this.shape.widthScale,
            0.48,
            1.08,
        );
        const shoulderY = THREE.MathUtils.lerp(half - 1.35, half - 0.72, this.shape.topCut);
        const neckRadius = radius * this.shape.topNeck;
        const profile = [
            { y: -half + 0.02, radius: radius * this.shape.bottomNeck },
            { y: -half + 0.18, radius: radius * 0.96 },
            { y: -half + 0.52, radius: radius * this.shape.bodyBulge },
            { y: 0, radius: radius * this.shape.bodyBulge },
            { y: shoulderY, radius: radius * this.shape.bodyBulge },
            { y: half - 0.52, radius: radius * this.shape.shoulder },
            { y: half - 0.34, radius: neckRadius * 1.08 },
            { y: half - 0.04, radius: neckRadius },
        ];
        const body = new THREE.Mesh(this.createProfileBodyGeometry(profile, 192), this.materials.label);
        body.castShadow = true;
        body.receiveShadow = true;
        this.canModel.add(body);

        const capHeight = 0.26 * this.shape.lidScale;
        const cap = new THREE.Mesh(
            new THREE.CylinderGeometry(neckRadius * 1.08, neckRadius * 1.04, capHeight, 96),
            this.materials.coloredCap,
        );
        cap.position.y = half + capHeight / 2;
        cap.castShadow = true;
        this.canModel.add(cap);

        this.addHorizontalRing(neckRadius * 1.12, 0.018, half - 0.02, this.materials.rim);
        this.addHorizontalRing(radius * this.shape.bottomNeck, 0.018, -half + 0.035, this.materials.rim);

        this.canBottomY = -half;
        this.syncStageFloor();
        this.refreshCameraDistance();
    }

    rebuildNoodleCup() {
        this.clearGroup(this.canModel);

        const height = this.shape.height;
        const half = height / 2;
        const textureAspect = this.texture.image.width / this.texture.image.height;
        const topRadius = THREE.MathUtils.clamp(
            ((textureAspect * height) / (Math.PI * 3.45)) * this.shape.widthScale * this.shape.topNeck,
            0.82,
            1.72,
        );
        const bottomRadius = topRadius * this.shape.bottomNeck;
        const profile = [
            { y: -half + 0.05, radius: bottomRadius },
            { y: -half + 0.18, radius: bottomRadius * 1.04 },
            { y: 0, radius: THREE.MathUtils.lerp(bottomRadius, topRadius, 0.48) * this.shape.bodyBulge },
            { y: half - 0.24, radius: topRadius * this.shape.shoulder },
            { y: half - 0.05, radius: topRadius },
        ];
        const body = new THREE.Mesh(this.createProfileBodyGeometry(profile, 192), this.materials.label);
        body.castShadow = true;
        body.receiveShadow = true;
        this.canModel.add(body);

        const lid = new THREE.Mesh(new THREE.CylinderGeometry(topRadius * 1.03, topRadius * 0.96, 0.11 * this.shape.lidScale, 128), this.materials.cupInside);
        lid.position.y = half + 0.02;
        lid.castShadow = true;
        lid.receiveShadow = true;
        this.canModel.add(lid);

        this.addHorizontalRing(topRadius * 1.03, 0.045 * this.shape.lidScale, half + 0.02, this.materials.rim);
        this.addHorizontalRing(bottomRadius * 1.02, 0.025, -half + 0.03, this.materials.rim);

        this.canBottomY = -half;
        this.syncStageFloor();
        this.refreshCameraDistance();
    }

    rebuildPouch({ slim, candy }) {
        this.clearGroup(this.canModel);

        const height = this.shape.height;
        const width = (slim ? 1.34 : candy ? 2.02 : 2.38) * this.shape.widthScale;
        const depth = (slim ? 0.26 : candy ? 0.34 : 0.48) * this.shape.bodyBulge;
        const sealHeight = THREE.MathUtils.clamp(0.15 * this.shape.lidScale, 0.08, 0.24);
        const sideWidth = slim ? 0.055 : 0.085;

        const front = new THREE.Mesh(this.createPouchPanelGeometry(width, height, depth, true), this.materials.softLabel);
        front.castShadow = true;
        front.receiveShadow = true;
        this.canModel.add(front);

        const back = new THREE.Mesh(this.createPouchPanelGeometry(width, height, depth, false), this.materials.pouchBack);
        back.castShadow = true;
        back.receiveShadow = true;
        this.canModel.add(back);

        const topSeal = new THREE.Mesh(new THREE.BoxGeometry(width * this.shape.topNeck, sealHeight, depth * 1.04), this.materials.pouchSeal);
        topSeal.position.y = height / 2 - sealHeight * 0.72;
        topSeal.castShadow = true;
        this.canModel.add(topSeal);

        const bottomSeal = new THREE.Mesh(new THREE.BoxGeometry(width * this.shape.bottomNeck, sealHeight, depth * 0.96), this.materials.pouchSeal);
        bottomSeal.position.y = -height / 2 + sealHeight * 0.72;
        bottomSeal.castShadow = true;
        this.canModel.add(bottomSeal);

        const leftSeal = new THREE.Mesh(new THREE.BoxGeometry(sideWidth, height * 0.92, depth * 0.9), this.materials.pouchSeal);
        leftSeal.position.x = -width / 2 + sideWidth / 2;
        const rightSeal = leftSeal.clone();
        rightSeal.position.x = width / 2 - sideWidth / 2;
        this.canModel.add(leftSeal, rightSeal);

        this.canBottomY = -height / 2;
        this.syncStageFloor();
        this.refreshCameraDistance();
    }

    rebuildCerealBox() {
        this.clearGroup(this.canModel);

        const height = this.shape.height;
        const width = 2.16 * this.shape.widthScale;
        const depth = 0.74 * this.shape.bodyBulge;
        const geometry = new THREE.BoxGeometry(width, height, depth, 1, 1, 1);
        const box = new THREE.Mesh(geometry, [
            this.materials.boxSide,
            this.materials.boxSide,
            this.materials.boxSide,
            this.materials.boxSide,
            this.materials.label,
            this.materials.boxBack,
        ]);
        box.castShadow = true;
        box.receiveShadow = true;
        this.canModel.add(box);

        const edges = new THREE.LineSegments(
            new THREE.EdgesGeometry(geometry),
            this.materials.edge,
        );
        this.canModel.add(edges);

        const topFlap = new THREE.Mesh(
            new THREE.BoxGeometry(width * 0.92, 0.055 * this.shape.lidScale, depth * 0.92),
            this.materials.pouchSeal,
        );
        topFlap.position.y = height / 2 + 0.035;
        this.canModel.add(topFlap);

        this.canBottomY = -height / 2;
        this.syncStageFloor();
        this.refreshCameraDistance();
    }

    getCanDimensions() {
        const height = this.shape.height;
        const textureAspect = this.texture.image.width / this.texture.image.height;
        const radius = THREE.MathUtils.clamp(
            ((textureAspect * height) / (Math.PI * 2)) * this.shape.widthScale,
            0.95,
            1.48,
        );

        return {
            height,
            radius,
            topY: height / 2,
            bottomY: -height / 2,
        };
    }

    createCanBodyGeometry(height, radius, radialSegments) {
        const half = height / 2;
        const topCut = this.shape.topCut;
        const topShoulderFar = THREE.MathUtils.lerp(0.62, 0.38, topCut);
        const topShoulderNear = THREE.MathUtils.lerp(0.34, 0.2, topCut);
        const topTaper = THREE.MathUtils.lerp(0.24, 0.14, topCut);
        const topNeckHigh = THREE.MathUtils.lerp(0.14, 0.078, topCut);
        const bottomBlend = THREE.MathUtils.lerp(this.shape.bottomNeck, 0.95, 0.55);
        const profile = [
            { y: -half + 0.055, radius: radius * this.shape.bottomNeck },
            { y: -half + 0.08, radius: radius * bottomBlend },
            { y: -half + 0.135, radius: radius * 0.98 },
            { y: -half + 0.24, radius: radius * THREE.MathUtils.lerp(1, this.shape.shoulder, 0.55) },
            { y: -half + 0.42, radius: radius * this.shape.shoulder },
            { y: -half + 0.72, radius: radius * THREE.MathUtils.lerp(this.shape.bodyBulge, this.shape.shoulder, 0.35) },
            { y: -half + 1.1, radius: radius * this.shape.bodyBulge },
            { y: 0, radius: radius * this.shape.bodyBulge },
            { y: half - 1.1, radius: radius * this.shape.bodyBulge },
            { y: half - topShoulderFar, radius: radius * THREE.MathUtils.lerp(this.shape.bodyBulge, this.shape.shoulder, 0.45) },
            { y: half - topShoulderNear, radius: radius * this.shape.shoulder },
            { y: half - topTaper, radius: radius * THREE.MathUtils.lerp(this.shape.shoulder, 0.98, 0.55) },
            { y: half - topNeckHigh, radius: radius * THREE.MathUtils.lerp(this.shape.topNeck, 0.94, 0.2) },
            { y: half - 0.058, radius: radius * this.shape.topNeck },
        ];
        const positions = [];
        const uvs = [];
        const indices = [];
        const minY = profile[0].y;
        const maxY = profile[profile.length - 1].y;

        profile.forEach((point) => {
            const v = (point.y - minY) / (maxY - minY);

            for (let column = 0; column <= radialSegments; column += 1) {
                const u = column / radialSegments;
                const theta = u * Math.PI * 2;
                positions.push(Math.sin(theta) * point.radius, point.y, Math.cos(theta) * point.radius);
                uvs.push(u, v);
            }
        });

        for (let row = 0; row < profile.length - 1; row += 1) {
            for (let column = 0; column < radialSegments; column += 1) {
                const a = row * (radialSegments + 1) + column;
                const b = (row + 1) * (radialSegments + 1) + column;
                const c = a + 1;
                const d = b + 1;
                indices.push(a, c, b, c, d, b);
            }
        }

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
        geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
        geometry.setIndex(indices);
        geometry.computeVertexNormals();

        return geometry;
    }

    createProfileBodyGeometry(profile, radialSegments) {
        const positions = [];
        const uvs = [];
        const indices = [];
        const minY = profile[0].y;
        const maxY = profile[profile.length - 1].y;

        profile.forEach((point) => {
            const v = (point.y - minY) / (maxY - minY);

            for (let column = 0; column <= radialSegments; column += 1) {
                const u = column / radialSegments;
                const theta = u * Math.PI * 2;
                positions.push(Math.sin(theta) * point.radius, point.y, Math.cos(theta) * point.radius);
                uvs.push(u, v);
            }
        });

        for (let row = 0; row < profile.length - 1; row += 1) {
            for (let column = 0; column < radialSegments; column += 1) {
                const a = row * (radialSegments + 1) + column;
                const b = (row + 1) * (radialSegments + 1) + column;
                const c = a + 1;
                const d = b + 1;
                indices.push(a, c, b, c, d, b);
            }
        }

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
        geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
        geometry.setIndex(indices);
        geometry.computeVertexNormals();

        return geometry;
    }

    createPouchPanelGeometry(width, height, depth, front) {
        const columns = 34;
        const rows = 46;
        const positions = [];
        const uvs = [];
        const indices = [];
        const sign = front ? 1 : -1;

        for (let row = 0; row <= rows; row += 1) {
            const v = row / rows;
            const y = (v - 0.5) * height;
            const normalizedY = Math.abs(v - 0.5) * 2;

            for (let column = 0; column <= columns; column += 1) {
                const u = column / columns;
                const x = (u - 0.5) * width;
                const normalizedX = Math.abs(u - 0.5) * 2;
                const centralBulge = (1 - normalizedX ** 2) * (1 - normalizedY ** 2) * depth * 0.58;
                const wrinkle = Math.sin(u * Math.PI * 5.5 + v * 2.1) * Math.sin(v * Math.PI * 3.2) * depth * 0.035;
                const sealFlattening = normalizedY > 0.86 ? (normalizedY - 0.86) * depth * 1.4 : 0;
                const z = sign * (depth / 2 + Math.max(0, centralBulge - sealFlattening) + wrinkle);

                positions.push(x, y, z);
                uvs.push(front ? u : 1 - u, v);
            }
        }

        for (let row = 0; row < rows; row += 1) {
            for (let column = 0; column < columns; column += 1) {
                const a = row * (columns + 1) + column;
                const b = (row + 1) * (columns + 1) + column;
                const c = a + 1;
                const d = b + 1;

                if (front) {
                    indices.push(a, c, b, c, d, b);
                } else {
                    indices.push(a, b, c, c, b, d);
                }
            }
        }

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
        geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
        geometry.setIndex(indices);
        geometry.computeVertexNormals();

        return geometry;
    }

    addCapDetails(radius, topY, bottomY) {
        const lidRadius = radius * this.shape.lidScale;
        const topDisc = new THREE.Mesh(new THREE.CircleGeometry(lidRadius * 0.82, 160), this.materials.cap);
        topDisc.rotation.x = -Math.PI / 2;
        topDisc.position.y = topY + 0.017;
        topDisc.receiveShadow = true;
        this.canModel.add(topDisc);

        const bottomDisc = new THREE.Mesh(new THREE.CircleGeometry(lidRadius * 0.78, 128), this.materials.cap);
        bottomDisc.rotation.x = Math.PI / 2;
        bottomDisc.position.y = bottomY - 0.017;
        this.canModel.add(bottomDisc);

        this.addHorizontalRing(lidRadius * 0.835, 0.035, topY + 0.03, this.materials.rim);
        this.addHorizontalRing(lidRadius * 0.765, 0.014, topY + 0.044, this.materials.cap);
        this.addHorizontalRing(lidRadius * 0.56, 0.008, topY + 0.05, this.materials.groove);
        this.addHorizontalRing(lidRadius * 0.3, 0.006, topY + 0.053, this.materials.groove);
        this.addHorizontalRing(lidRadius * 0.815, 0.026, bottomY - 0.03, this.materials.rim);
        this.addHorizontalRing(lidRadius * 0.7, 0.012, bottomY - 0.045, this.materials.cap);

        const scoreLine = new THREE.Mesh(new THREE.TorusGeometry(0.245 * this.shape.lidScale, 0.008, 12, 96), this.materials.groove);
        scoreLine.rotation.x = Math.PI / 2;
        scoreLine.scale.set(1.18, 0.7, 1);
        scoreLine.position.set(0.39, topY + 0.073, 0.1);
        this.canModel.add(scoreLine);

        const opening = new THREE.Mesh(this.createEllipseGeometry(0.29 * this.shape.lidScale, 0.17 * this.shape.lidScale, 80), this.materials.black);
        opening.rotation.x = -Math.PI / 2;
        opening.rotation.z = -0.08;
        opening.position.set(0.39, topY + 0.079, 0.1);
        opening.renderOrder = 2;
        this.canModel.add(opening);

        const pullTab = this.createPullTab();
        pullTab.rotation.set(-Math.PI / 2, 0, -0.12);
        pullTab.position.set(-0.03, topY + 0.095, 0.015);
        pullTab.scale.setScalar(this.shape.lidScale);
        pullTab.castShadow = true;
        pullTab.receiveShadow = true;
        this.canModel.add(pullTab);

        const tabShadow = new THREE.Mesh(this.createEllipseGeometry(0.18 * this.shape.lidScale, 0.08 * this.shape.lidScale, 48), this.materials.tabShadow);
        tabShadow.rotation.x = -Math.PI / 2;
        tabShadow.position.set(-0.02, topY + 0.071, 0.14);
        this.canModel.add(tabShadow);

        const rivetBase = new THREE.Mesh(new THREE.CylinderGeometry(0.105 * this.shape.lidScale, 0.13 * this.shape.lidScale, 0.052, 48), this.materials.cap);
        rivetBase.position.set(-0.02, topY + 0.095, 0.18);
        rivetBase.castShadow = true;
        this.canModel.add(rivetBase);

        const rivet = new THREE.Mesh(new THREE.CylinderGeometry(0.062 * this.shape.lidScale, 0.079 * this.shape.lidScale, 0.03, 40), this.materials.cap);
        rivet.position.set(-0.02, topY + 0.14, 0.18);
        rivet.castShadow = true;
        this.canModel.add(rivet);
    }

    addHorizontalRing(radius, tube, y, material) {
        const ring = new THREE.Mesh(new THREE.TorusGeometry(radius, tube, 18, 180), material);
        ring.rotation.x = Math.PI / 2;
        ring.position.y = y;
        ring.castShadow = true;
        ring.receiveShadow = true;
        this.canModel.add(ring);
    }

    createPullTab() {
        const shape = this.createRoundedRectShape(0.42, 1.12, 0.2);
        const fingerHole = new THREE.Path();
        fingerHole.absellipse(0, 0.28, 0.125, 0.195, 0, Math.PI * 2, true);
        const rivetHole = new THREE.Path();
        rivetHole.absellipse(0, -0.17, 0.09, 0.07, 0, Math.PI * 2, true);
        shape.holes.push(fingerHole, rivetHole);

        const geometry = new THREE.ExtrudeGeometry(shape, {
            depth: 0.035,
            bevelEnabled: true,
            bevelSegments: 5,
            bevelSize: 0.014,
            bevelThickness: 0.012,
            curveSegments: 36,
        });
        geometry.translate(0, 0, -0.0175);
        geometry.computeVertexNormals();

        return new THREE.Mesh(geometry, this.materials.cap);
    }

    createRoundedRectShape(width, height, radius) {
        const x = -width / 2;
        const y = -height / 2;
        const shape = new THREE.Shape();
        shape.moveTo(x + radius, y);
        shape.lineTo(x + width - radius, y);
        shape.quadraticCurveTo(x + width, y, x + width, y + radius);
        shape.lineTo(x + width, y + height - radius);
        shape.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        shape.lineTo(x + radius, y + height);
        shape.quadraticCurveTo(x, y + height, x, y + height - radius);
        shape.lineTo(x, y + radius);
        shape.quadraticCurveTo(x, y, x + radius, y);

        return shape;
    }

    createEllipseGeometry(radiusX, radiusY, segments) {
        const shape = new THREE.Shape();
        shape.absellipse(0, 0, radiusX, radiusY, 0, Math.PI * 2, false);

        return new THREE.ShapeGeometry(shape, segments);
    }

    normalizeModelType(modelType) {
        const allowed = ['can', 'bottle', 'chip_bag', 'noodle_cup', 'candy_bag', 'candy_stick_bag', 'cereal_box'];

        return allowed.includes(modelType) ? modelType : 'can';
    }

    preferredRotationY() {
        return FLAT_FRONT_TYPES.includes(this.modelType) ? 0 : Math.PI;
    }

    bindInput() {
        const { signal } = this.abortController;

        this.canvas.addEventListener('pointerdown', (event) => {
            this.pointer.active = true;
            this.pointer.x = event.clientX;
            this.pointer.y = event.clientY;
            this.pointer.velocityX = 0;
            this.pointer.velocityY = 0;
            this.pointer.lastMove = performance.now();
            this.canvas.setPointerCapture(event.pointerId);
            this.canvas.classList.add('is-dragging');
        }, { signal });

        this.canvas.addEventListener('pointermove', (event) => {
            if (!this.pointer.active) {
                return;
            }

            const now = performance.now();
            const dx = event.clientX - this.pointer.x;
            const dy = event.clientY - this.pointer.y;
            const elapsed = Math.max(now - this.pointer.lastMove, 16);
            this.targetRotation.y += dx * 0.008;
            this.targetRotation.x = THREE.MathUtils.clamp(this.targetRotation.x + dy * 0.004, -0.48, 0.42);
            this.pointer.velocityY = (dx * 0.008) / elapsed * 16;
            this.pointer.velocityX = (dy * 0.004) / elapsed * 16;
            this.pointer.x = event.clientX;
            this.pointer.y = event.clientY;
            this.pointer.lastMove = now;
        }, { signal });

        window.addEventListener('pointerup', () => this.releasePointer(), { signal });
        window.addEventListener('pointercancel', () => this.releasePointer(), { signal });
        window.addEventListener('resize', () => this.resize(), { signal });
        this.canvas.addEventListener('wheel', (event) => {
            event.preventDefault();
            this.cameraDistance = THREE.MathUtils.clamp(this.cameraDistance + event.deltaY * 0.003, 5.6, 10.4);
        }, { passive: false, signal });
    }

    releasePointer() {
        this.pointer.active = false;
        this.canvas.classList.remove('is-dragging');
    }

    resize() {
        const rect = this.canvas.getBoundingClientRect();
        const width = Math.max(1, Math.floor(rect.width));
        const height = Math.max(1, Math.floor(rect.height));

        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, MAX_PIXEL_RATIO));
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        this.sceneTargetY = width < 720 ? 0.78 : 0.38;
        this.canGroup.position.y = this.sceneTargetY;
        this.syncStageFloor();
        this.refreshCameraDistance();
        this.camera.position.set(0, this.sceneTargetY + (width < 720 ? 0.46 : 0.24), this.cameraDistance);
        this.camera.updateProjectionMatrix();
    }

    syncStageFloor() {
        if (this.stageFloor) {
            this.stageFloor.position.y = this.sceneTargetY + this.canBottomY - 0.28;
        }
    }

    refreshCameraDistance() {
        const width = this.canvas.getBoundingClientRect().width;
        const baseDistanceByType = {
            bottle: width < 720 ? 10.4 : 8.6,
            can: width < 720 ? 9.55 : 7.8,
            candy_bag: width < 720 ? 8.8 : 6.8,
            candy_stick_bag: width < 720 ? 8.7 : 6.5,
            cereal_box: width < 720 ? 9.2 : 7.2,
            chip_bag: width < 720 ? 8.9 : 6.9,
            noodle_cup: width < 720 ? 7.2 : 5.9,
        };
        const baseDistance = baseDistanceByType[this.modelType] || baseDistanceByType.can;
        const heightOffset = (this.shape.height - SHAPE_DEFAULTS.height) * (FLAT_FRONT_TYPES.includes(this.modelType) ? 0.45 : 0.6);
        const widthOffset = (this.shape.widthScale - SHAPE_DEFAULTS.widthScale) * (FLAT_FRONT_TYPES.includes(this.modelType) ? 1.7 : 2.4);
        this.cameraDistance = THREE.MathUtils.clamp(baseDistance + heightOffset + widthOffset, 4.8, 12);
    }

    animate() {
        this.animationFrame = window.requestAnimationFrame(() => this.animate());

        if (!this.pointer.active && !this.reduceMotion) {
            this.targetRotation.y += 0.0022;
        }

        if (!this.pointer.active) {
            this.targetRotation.y += this.pointer.velocityY;
            this.targetRotation.x = THREE.MathUtils.clamp(this.targetRotation.x + this.pointer.velocityX, -0.48, 0.42);
            this.pointer.velocityY *= 0.92;
            this.pointer.velocityX *= 0.88;
        }

        this.currentRotation.lerp(this.targetRotation, 0.1);
        this.canGroup.rotation.x = this.currentRotation.x;
        this.canGroup.rotation.y = this.currentRotation.y;
        this.camera.position.z += (this.cameraDistance - this.camera.position.z) * 0.08;
        this.camera.lookAt(0, this.sceneTargetY, 0);
        this.renderer.render(this.scene, this.camera);
    }

    clearGroup(group) {
        [...group.children].forEach((child) => {
            child.traverse((node) => {
                node.geometry?.dispose?.();
            });
            group.remove(child);
        });
    }
}
