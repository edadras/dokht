"""
رندرِ سرور: لباسِ دوخته‌شده روی همان تنی که رویش دوخته شد، در استودیو.

ورودی: پروندهٔ کار (payload از ClothPreviewService) و خروجیِ sew.mjs (مش‌های
دوخته‌شده + حلقه‌های بدن). خروجی: پنج نمای استودیویی (جلو، پهلوی راست، پشت،
آزمونِ وزنِ آب، آزمونِ جریانِ هوا)، یک برگهٔ کنارِ همِ همان پنج نما با عنوان،
مدلِ GLB و manifest.json.

قراردادها:
  - حل‌کننده: متر، y رو به بالا، z مثبت جلو. Blender: z رو به بالا؛ پس (x, -z, y).
  - حلقه‌های بدن (sewn.body): سانتی‌متر، y رو به پایین از فرقِ سر (mannequin.js).
  - مانکن مثل مانکنِ خیاطی است: تنه تا فاق، پایهٔ فلزی تا زمین، بی‌سر. بازو فقط
    وقتی که لباس آستین دارد.
  - Cycles روی CPU، چون کانتینر GPU و نمایشگر ندارد و EEVEE بی‌آن بالا نمی‌آید.
"""
import bpy, json, math, os, sys
from mathutils import Vector

job_path = sys.argv[sys.argv.index('--') + 1]
sewn_path = sys.argv[sys.argv.index('--') + 2]
with open(job_path, encoding='utf-8') as stream:
    job = json.load(stream)
with open(sewn_path, encoding='utf-8') as stream:
    sewn = json.load(stream)
p = job.get('payload') or {}
# آرایهٔ خالیِ PHP در JSON فهرست است، نه شیء
avatar = p.get('avatar') if isinstance(p.get('avatar'), dict) else {}
garment = p.get('garment') if isinstance(p.get('garment'), dict) else {}
fabric = p.get('fabric') if isinstance(p.get('fabric'), dict) else {}
out = os.path.join(os.environ.get('RENDER_OUT', '/data/app/public/renders'), job['id'])
os.makedirs(out, exist_ok=True)
SAMPLES = int(os.environ.get('RENDER_SAMPLES', '96'))
WIDTH, HEIGHT = 720, 900
N = 48

bpy.ops.object.select_all(action='SELECT')
bpy.ops.object.delete(use_global=False)


def mat(name, color, rough=.55, metallic=0.0, sheen=0.0):
    m = bpy.data.materials.new(name)
    m.diffuse_color = (*color, 1)
    m.use_nodes = True
    bsdf = m.node_tree.nodes.get('Principled BSDF')
    bsdf.inputs['Base Color'].default_value = (*color, 1)
    bsdf.inputs['Roughness'].default_value = rough
    bsdf.inputs['Metallic'].default_value = metallic
    if sheen and 'Sheen Weight' in bsdf.inputs:
        bsdf.inputs['Sheen Weight'].default_value = sheen
    return m


def smooth(ob, levels=1):
    for poly in ob.data.polygons:
        poly.use_smooth = True
    if levels:
        sub = ob.modifiers.new('نرمی', 'SUBSURF')
        sub.levels = levels
        sub.render_levels = levels
    return ob


def loft(name, rings, material, cap=True, levels=1):
    """یک مش از حلقه‌های پشتِ سرِ هم؛ هر حلقه فهرستی از نقطه‌های (x, y, z) در دستگاهِ Blender."""
    verts, faces = [], []
    n = len(rings[0])
    for ring in rings:
        verts.extend(ring)
    for r in range(len(rings) - 1):
        for i in range(n):
            j = (i + 1) % n
            faces.append((r * n + i, r * n + j, (r + 1) * n + j, (r + 1) * n + i))
    if cap:
        faces.append(tuple(range(n)))
        faces.append(tuple(reversed(range((len(rings) - 1) * n, len(rings) * n))))
    mesh = bpy.data.meshes.new(name)
    mesh.from_pydata(verts, [], faces)
    mesh.update()
    ob = bpy.data.objects.new(name, mesh)
    bpy.context.collection.objects.link(ob)
    ob.data.materials.append(material)
    return smooth(ob, levels)


def cylinder(name, a, b, radius, material):
    mid = (Vector(a) + Vector(b)) / 2
    direction = Vector(b) - Vector(a)
    bpy.ops.mesh.primitive_cylinder_add(vertices=32, radius=radius, depth=direction.length, location=mid)
    ob = bpy.context.object
    ob.name = name
    ob.data.materials.append(material)
    ob.rotation_mode = 'QUATERNION'
    ob.rotation_quaternion = direction.to_track_quat('Z', 'Y')
    bpy.ops.object.shade_smooth()
    return ob


def circle(cx, cy, cz, r, zc=0.0):
    return [(cx + r * math.cos(2 * math.pi * i / N), -(zc + r * math.sin(2 * math.pi * i / N)), cy) for i in range(N)]


# ── مواد: مانکنِ خیاطی (کتانِ روشن)، فلزِ پایه، پارچه ─────────────────────────
form = mat('مانکن', (.66, .62, .56), .92)
steel = mat('فلز', (.55, .55, .56), .35, .9)
cloth_hex = str(fabric.get('color', '#eeeae3')).lstrip('#')
def linear(c):
    """sRGB → خطی؛ Blender رنگِ پایه را خطی می‌خواهد. بی‌این، کرم (#e8ddc8) سفید رندر می‌شد."""
    return c / 12.92 if c <= .04045 else ((c + .055) / 1.055) ** 2.4


try:
    cloth_rgb = tuple(linear(int(cloth_hex[i:i + 2], 16) / 255) for i in (0, 2, 4))
except Exception:
    cloth_rgb = (.82, .78, .71)
cloth = mat('پارچه', cloth_rgb, max(.35, .82 - float(fabric.get('sheen', .15)) * .5), sheen=.35)
_alpha = 1 - max(0.0, min(.6, float(fabric.get('transparency', 0) or 0)))
if _alpha < 1:
    cloth.node_tree.nodes['Principled BSDF'].inputs['Alpha'].default_value = _alpha
    cloth.blend_method = 'BLEND'
# بافتِ ریزِ پارچه: نویزِ ریز روی نرمال، تا سطح مثل پلاستیکِ صاف نباشد
_nodes = cloth.node_tree.nodes
_links = cloth.node_tree.links
_noise = _nodes.new('ShaderNodeTexNoise')
_noise.inputs['Scale'].default_value = 900
_noise.inputs['Detail'].default_value = 4
_bump = _nodes.new('ShaderNodeBump')
_bump.inputs['Strength'].default_value = .12
_bump.inputs['Distance'].default_value = .0008
_links.new(_noise.outputs['Fac'], _bump.inputs['Height'])
_links.new(_bump.outputs['Normal'], _nodes['Principled BSDF'].inputs['Normal'])

has_sleeves = any((m.get('role') == 'sleeve') for m in sewn.get('meshes', []))


def body_from_rings(body):
    """
    مانکن از همان حلقه‌هایی که پارچه رویشان دوخته شد.

    پیش از این مانکن از کره و استوانه با نسبت‌های ثابت ساخته می‌شد و لباسِ
    دوخته‌شده روی تنِ دیگری می‌نشست. حلقه‌ها به سانتی‌متر و y رو به پایین از فرقِ
    سرند؛ z مثبت جلوست. هر حلقه نیم‌پهنا (rx)، نیم‌عمقِ جلو و پشت و مرکزِ خودش
    روی z را دارد. مثلِ مانکنِ خیاطی، از گردن تا فاق و بعد پایه تا زمین.
    """
    H = float(body['height']) / 100
    up = lambda y_down: H - float(y_down) / 100

    def ellipse(rx, front, back, zc, y):
        ring = []
        for i in range(N):
            a = 2 * math.pi * i / N
            s = math.sin(a)
            depth = front if s >= 0 else back
            ring.append((rx * math.cos(a) / 100, -(zc + depth * s) / 100, y))
        return ring

    neck_level = float(body['level']['neck'])
    crotch_up = up(body['level']['crotch'])
    hull = sewn.get('hull')
    if hull:
        # همان پوستی که پارچه رویش نشسته (برخوردگر، با گودیِ زیرِ بغل)، سه میلی‌متر تو‌رفته تا از زیرِ پارچه بیرون نزند
        rows = sorted([r for r in hull if r[0] >= crotch_up - 1e-6 and r[0] <= up(neck_level) + 1e-6], key=lambda r: r[0])
        rings = [ellipse(max(.5, r[1] * 100 - .3), max(.5, r[3] * 100 - .3), max(.5, r[4] * 100 - .3), (r[5] if len(r) > 5 else 0) * 100, r[0]) for r in rows]
        top_ring = rows[-1]
        rings.append(ellipse(top_ring[1] * 96, top_ring[3] * 96, top_ring[4] * 96, (top_ring[5] if len(top_ring) > 5 else 0) * 100, top_ring[0] + .02))
    else:
        torso = sorted([r for r in body['torso'] if float(r['y']) >= neck_level - 1e-6], key=lambda r: -float(r['y']))
        rings = [ellipse(float(r['rx']), float(r['front']), float(r['back']), float(r.get('z', 0)), up(r['y'])) for r in torso]
        # سرِ مانکن: همان حلقهٔ گردن، دو سانتی‌متر بالاتر و کمی تنگ‌تر، تا بسته شود
        top = torso[-1]
        rings.append(ellipse(float(top['rx']) * .96, float(top['front']) * .96, float(top['back']) * .96, float(top.get('z', 0)), up(top['y']) + .02))
    loft('تنه', rings, form, levels=2)
    # پایهٔ مانکن: میلهٔ فلزی از زیرِ فاق تا زمین و صفحهٔ گرد
    crotch = up(body['level']['crotch'])
    cylinder('میله', (0, 0, .02), (0, 0, crotch + .02), .012, steel)
    bpy.ops.mesh.primitive_cylinder_add(vertices=64, radius=.22, depth=.02, location=(0, 0, .01))
    base = bpy.context.object
    base.name = 'پایه'
    base.data.materials.append(steel)
    bpy.ops.object.shade_smooth()
    # پا: وقتی لباس از فاق پایین‌تر می‌رود (شلوار، شورت، دامن)، مانکن پا دارد
    ys = [m['positions'][i] for m in sewn.get('meshes', []) for i in range(1, len(m['positions']), 3)]
    if ys and min(ys) < crotch - .03 and body.get('leg'):
        for side in (-1, 1):
            lrings = []
            # همان محورِ برخوردگر: x ثابت (leg[0].x)، نه x هر حلقه — وگرنه پای مانکن از داخلِ پای شلوار بیرون می‌زد
            leg_x = float(body['leg'][0].get('x', 9)) / 100
            for row in body['leg']:
                cy = crotch - float(row['y']) / 100
                r = max(.01, float(row['r']) / 100 - .003)
                cx = side * leg_x
                lrings.append([(cx + r * math.cos(2 * math.pi * i / N), -(r * math.sin(2 * math.pi * i / N)), cy) for i in range(N)])
            loft('پا', lrings, form)
    if has_sleeves:
        arm = body['arm']
        ring0 = body['shoulderRing']
        joint_x = (float(ring0['rx']) - 0.35 * float(arm[0]['r'])) / 100
        joint_y = up(float(ring0['y']) + 0.5 * float(arm[0]['r']))
        tilt = float(body.get('armTilt', 0.085))
        arm_z = float(body.get('armZ', 0)) / 100
        for side in (-1, 1):
            arings = []
            for row in arm:
                along = float(row['y']) / 100
                cx = side * (joint_x + along * math.sin(tilt))
                cy = joint_y - along * math.cos(tilt)
                arings.append(circle(cx, cy, 0, float(row['r']) / 100, arm_z))
            loft('بازو', arings, form)
    return H


if sewn.get('body') and sewn['body'].get('torso'):
    height = body_from_rings(sewn['body'])
else:
    height = max(1.45, min(1.9, float(avatar.get('height', 165)) / 100))
    bust = float(avatar.get('bust', 90)) / 100
    hip = float(avatar.get('hip', 98)) / 100
    z_waist, z_bust, z_shoulder = height * .55, height * .69, height * .79
    bpy.ops.mesh.primitive_uv_sphere_add(segments=48, ring_count=24, location=(0, 0, (z_waist + z_bust) / 2))
    torso_ob = bpy.context.object
    torso_ob.scale = (bust * .27, bust * .18, (z_bust - z_waist) * .75)
    torso_ob.data.materials.append(form)
    bpy.ops.mesh.primitive_uv_sphere_add(segments=48, ring_count=24, location=(0, 0, z_waist - .11))
    hips_ob = bpy.context.object
    hips_ob.scale = (hip * .26, hip * .18, .22)
    hips_ob.data.materials.append(form)
    cylinder('میله', (0, 0, .02), (0, 0, z_waist - .3), .012, steel)


def garment_mesh(mode='dry'):
    """
    لباسِ دوخته‌شده، در سه حالت: خشک، با وزنِ آب (پارچه سنگین‌تر و کشیده‌تر)،
    و در بادِ افقی از چپ به راست (دامن به راست می‌رود و موج برمی‌دارد). دو حالتِ
    آخر تغییرِ شکلِ هندسی روی همان مشِ حل‌شده‌اند، نه شبیه‌سازیِ دوباره.
    """
    if sewn.get('meshes'):
        verts, faces = [], []
        all_y = [mesh['positions'][i] for mesh in sewn['meshes'] for i in range(1, len(mesh['positions']), 3)]
        low, high = min(all_y), max(all_y)
        body = sewn.get('body') or {}
        # از خطِ باسن به پایین تغییر می‌کند؛ بالاتنه سرِ جایش می‌ماند
        hip_y = (float(body['height']) - float(body['level']['hip'])) / 100 if body.get('level') else low + (high - low) * .45
        # بالاتنهٔ کوتاه فقط کمی تکان می‌خورد؛ دامنِ بلند تا ته
        span = max(.35, hip_y - low)
        for part in sewn['meshes']:
            offset = len(verts)
            positions = part['positions']
            for i in range(0, len(positions), 3):
                x, vertical, depth = positions[i:i + 3]
                fall = max(0.0, min(1.0, (hip_y - vertical) / span))
                if mode == 'air':
                    # باد از چپ به راستِ تصویر (+x)؛ دامن هرچه پایین‌تر، بیشتر می‌رود و موج برمی‌دارد
                    x += .38 * fall ** 1.6
                    depth += .04 * math.sin(vertical * 22 + x * 9) * fall
                elif mode == 'water':
                    # پارچهٔ خیس سنگین‌تر است: دامن کمی پایین‌تر و تنگ‌تر می‌افتد
                    vertical -= .03 * fall
                    x *= 1 - .06 * fall
                    depth *= 1 - .06 * fall
                verts.append((x, -depth, vertical))
            indices = part['indices']
            for i in range(0, len(indices), 3):
                faces.append((offset + indices[i], offset + indices[i + 1], offset + indices[i + 2]))
        mesh = bpy.data.meshes.new('لباس دوخته‌شده')
        mesh.from_pydata(verts, [], faces)
        mesh.update()
        ob = bpy.data.objects.new('لباس دوخته‌شده', mesh)
        bpy.context.collection.objects.link(ob)
        ob.data.materials.append(cloth)
        # هموارسازیِ اصلاحی: چین‌های ریزِ عددیِ حل‌کننده را می‌گیرد، چین‌های درشت می‌مانند
        cs = ob.modifiers.new('هموار', 'CORRECTIVE_SMOOTH')
        cs.factor = .5
        cs.iterations = 6
        cs.smooth_type = 'LENGTH_WEIGHTED'
        cs.use_only_smooth = True
        sol = ob.modifiers.new('ضخامت', 'SOLIDIFY')
        sol.thickness = .0022
        sol.offset = 1
        for poly in mesh.polygons:
            poly.use_smooth = True
        return ob

    # بی‌قطعه (الگوی بی‌دوخت): نمای پارامتریِ ساده
    lengths = garment.get('lengths', {})
    skirt_cm = float(lengths.get('skirt', 90) or 90)
    z_waist = height * .55
    z_bust = height * .69
    z_shoulder = height * .79
    bust = float(avatar.get('bust', 90)) / 100
    waist = float(avatar.get('waist', 72)) / 100
    hip = float(avatar.get('hip', 98)) / 100
    bottom = max(.04, z_waist - skirt_cm / 100)
    silhouette = garment.get('silhouette', 'a_line')
    flare = {'fitted': 1.03, 'straight': 1.12, 'a_line': 1.65, 'flared': 2.15}.get(silhouette, 1.4)
    zs = [bottom + (z_shoulder - bottom) * i / 22 for i in range(23)]
    verts = []
    faces = []
    n = 72
    for ri, z in enumerate(zs):
        body_x = (waist * .27 if z < z_bust else bust * .27)
        skirt_t = max(0, min(1, (z_waist - z) / max(.01, z_waist - bottom)))
        rx = body_x * (1 - skirt_t) + hip * .26 * (1 - skirt_t * .25) + hip * .26 * flare * skirt_t
        ry = (waist * .19 if z < z_bust else bust * .19) * (1 - skirt_t) + hip * .18 * (1 + skirt_t * .3)
        for i in range(n):
            a = 2 * math.pi * i / n
            fold = (.008 + .018 * skirt_t) * math.sin(a * 9 + ri * .28)
            x = (rx + fold) * math.cos(a)
            y = (ry + fold) * math.sin(a)
            if mode == 'air':
                wind = skirt_t ** 2
                x += .42 * wind
                y += .08 * math.sin(a * 3) * wind
            if mode == 'water':
                z -= .035 * skirt_t
            verts.append((x, y, z))
    for r in range(len(zs) - 1):
        for i in range(n):
            j = (i + 1) % n
            faces.append((r * n + i, r * n + j, (r + 1) * n + j, (r + 1) * n + i))
    mesh = bpy.data.meshes.new('لباس')
    mesh.from_pydata(verts, [], faces)
    mesh.update()
    ob = bpy.data.objects.new('لباس', mesh)
    bpy.context.collection.objects.link(ob)
    ob.data.materials.append(cloth)
    sol = ob.modifiers.new('ضخامت', 'SOLIDIFY')
    sol.thickness = .0025
    return smooth(ob, 1)


garment_ob = garment_mesh('dry')

# اشکال‌زدایی: RENDER_HIDE=form یا cloth یکی را از رندر برمی‌دارد
for _name in os.environ.get('RENDER_HIDE', '').split(','):
    if _name == 'form':
        for _ob in list(bpy.data.objects):
            if _ob.name.startswith(('تنه', 'بازو', 'میله', 'پایه')):
                _ob.hide_render = True
    elif _name == 'cloth':
        garment_ob.hide_render = True

# ── استودیو: کفِ روشن با سایهٔ نرم، پس‌زمینهٔ روشن، سه نورِ سطحی ──────────────
bpy.ops.mesh.primitive_plane_add(size=12, location=(0, 0, 0))
floor = bpy.context.object
floor.data.materials.append(mat('زمین', (.86, .86, .85), .95))
# پس‌زمینه از خودِ world می‌آید (خاکستریِ روشنِ یک‌دست، مثل عکسِ استودیو)؛ دیواری نیست تا نمای پشت هم همان را ببیند
for name, loc, energy, size in (('key', (2.6, -3.2, 3.4), 420, 3.0), ('fill', (-3.2, -2.0, 2.4), 200, 3.5), ('rim', (0, 2.4, 3.2), 160, 2.0)):
    bpy.ops.object.light_add(type='AREA', location=loc)
    light = bpy.context.object
    light.name = name
    light.data.energy = energy
    light.data.size = size
    light.data.shape = 'DISK'
    light.rotation_euler = (Vector((0, 0, height * .5)) - Vector(loc)).to_track_quat('-Z', 'Y').to_euler()

scene = bpy.context.scene
scene.render.engine = 'CYCLES'
scene.cycles.device = 'CPU'
scene.cycles.samples = SAMPLES
scene.cycles.use_denoising = True
try:
    scene.cycles.denoiser = 'OPENIMAGEDENOISE'
except TypeError:
    pass
scene.render.resolution_x = WIDTH
scene.render.resolution_y = HEIGHT
scene.render.resolution_percentage = 100
scene.render.image_settings.file_format = 'PNG'
scene.render.film_transparent = False
scene.world.use_nodes = True
scene.world.node_tree.nodes['Background'].inputs['Color'].default_value = (.66, .66, .65, 1)
scene.world.node_tree.nodes['Background'].inputs['Strength'].default_value = 1
scene.view_settings.exposure = -.5
try:
    scene.view_settings.view_transform = 'AgX'
except TypeError:
    scene.view_settings.view_transform = 'Filmic'

bpy.ops.object.camera_add()
camera = bpy.context.object
scene.camera = camera
camera.data.lens = 60
camera.data.sensor_fit = 'VERTICAL'


def frame():
    """قاب از خودِ لباس: از کمی زیرِ پایین‌ترین نقطهٔ لباس تا بالای گردنِ مانکن؛ لباسِ بلند تا زمین."""
    ys = [m['positions'][i] for m in sewn.get('meshes', []) for i in range(1, len(m['positions']), 3)]
    low = min(ys) if ys else 0.0
    top = max(max(ys) if ys else height * .8, height * .86)
    bottom = 0.0 if low < .25 else low - .12
    centre = (top + bottom) / 2
    extent = max(.9, top - bottom + .16)
    half_fov = math.atan((camera.data.sensor_height / 2) / camera.data.lens)
    return centre, extent / 2 / math.tan(half_fov) * 1.06


def shot(name, pos, mode='dry'):
    global garment_ob
    if mode != 'dry':
        bpy.data.objects.remove(garment_ob, do_unlink=True)
        garment_ob = garment_mesh(mode)
    centre, distance = frame()
    direction = Vector(pos).normalized()
    pos = (direction.x * distance, direction.y * distance, centre)
    camera.location = pos
    target = Vector((0, 0, centre))
    camera.rotation_euler = (target - Vector(pos)).to_track_quat('-Z', 'Y').to_euler()
    scene.render.filepath = os.path.join(out, name + '.png')
    bpy.ops.render.render(write_still=True)
    if mode != 'dry':
        bpy.data.objects.remove(garment_ob, do_unlink=True)
        garment_ob = garment_mesh('dry')


shot('front', (0, -1, 0))
shot('side', (1, 0, 0))
shot('back', (0, 1, 0))
shot('water', (0, -1, 0), 'water')
shot('airflow', (0, -1, 0), 'air')

scene.render.filepath = os.path.join(out, 'garment.glb')
bpy.ops.export_scene.gltf(filepath=scene.render.filepath, export_format='GLB', use_selection=False)


# ── برگهٔ کنارِ هم: پنج نما با عنوانِ بالا و شرحِ پایین، در یک صحنهٔ جدا ────────
def sheet():
    """
    صحنهٔ دوم: هر تصویر روی یک صفحهٔ تابان (Emission) با نوارِ سیاهِ عنوان و
    نوارِ روشنِ شرح؛ دوربینِ ارتوگرافیک ۱:۱. رنگ‌ها با view transform استاندارد
    دست نمی‌خورند. ۱ واحد = ۱۰۰ پیکسل.
    """
    panels = [
        ('front', '1) DRY FRONT', 'FRONT VIEW (DRY)'),
        ('side', '2) DRY RIGHT SIDE', 'RIGHT SIDE VIEW (DRY)'),
        ('back', '3) DRY BACK', 'BACK VIEW (DRY)'),
        ('water', '4) WATER-WEIGHT DRAPE TEST', 'FRONT VIEW (WATER-WEIGHT)'),
        ('airflow', '5) AIRFLOW DEFORMATION TEST', 'FRONT VIEW (AIRFLOW)'),
    ]
    head, foot, gap = .36, .70, .04
    pw, ph = WIDTH / 100, HEIGHT / 100
    total_w = len(panels) * pw + (len(panels) - 1) * gap
    total_h = head + ph + foot
    sc = bpy.data.scenes.new('Sheet')
    bpy.context.window.scene = sc
    sc.render.engine = 'CYCLES'
    sc.cycles.device = 'CPU'
    sc.cycles.samples = 16
    sc.render.resolution_x = int(total_w * 100)
    sc.render.resolution_y = int(total_h * 100)
    sc.render.image_settings.file_format = 'PNG'
    sc.view_settings.view_transform = 'Standard'
    sc.world = bpy.data.worlds.new('sheet-world')
    sc.world.use_nodes = True
    sc.world.node_tree.nodes['Background'].inputs['Color'].default_value = (.5, .5, .5, 1)
    sc.world.node_tree.nodes['Background'].inputs['Strength'].default_value = 1

    def emissive(name, color=None, image=None):
        m = bpy.data.materials.new(name)
        m.use_nodes = True
        nodes = m.node_tree.nodes
        links = m.node_tree.links
        for n in list(nodes):
            nodes.remove(n)
        outp = nodes.new('ShaderNodeOutputMaterial')
        emit = nodes.new('ShaderNodeEmission')
        emit.inputs['Strength'].default_value = 1
        if image is not None:
            tex = nodes.new('ShaderNodeTexImage')
            tex.image = image
            links.new(tex.outputs['Color'], emit.inputs['Color'])
        else:
            emit.inputs['Color'].default_value = (*color, 1)
        links.new(emit.outputs['Emission'], outp.inputs['Surface'])
        return m

    def rect(name, x, y, w, h, material, z=0):
        bpy.ops.mesh.primitive_plane_add(size=1, location=(x + w / 2, y + h / 2, z))
        ob = bpy.context.object
        ob.name = name
        ob.scale = (w, h, 1)
        ob.data.materials.append(material)
        return ob

    def text(body, x, y, size, material, z=.02):
        curve = bpy.data.curves.new(body, type='FONT')
        curve.body = body
        curve.size = size
        curve.align_x = 'CENTER'
        curve.align_y = 'CENTER'
        ob = bpy.data.objects.new(body, curve)
        ob.location = (x, y, z)
        sc.collection.objects.link(ob)
        ob.data.materials.append(material)
        return ob

    black = emissive('sheet-black', (.05, .05, .05))
    white = emissive('sheet-white', (1, 1, 1))
    light = emissive('sheet-light', (.94, .94, .94))
    dark = emissive('sheet-dark', (.12, .12, .12))
    for i, (key, title, caption) in enumerate(panels):
        x = i * (pw + gap)
        image = bpy.data.images.load(os.path.join(out, key + '.png'))
        image.colorspace_settings.name = 'sRGB'
        rect(key, x, foot, pw, ph, emissive('sheet-' + key, image=image))
        rect(key + '-head', x, foot + ph, pw, head, black)
        text(title, x + pw / 2, foot + ph + head / 2, .17, white)
        rect(key + '-foot', x, 0, pw, foot, light)
        text(caption, x + pw / 2, foot * .68, .15, dark)
        text('Sewn from the pattern pieces', x + pw / 2, foot * .40, .11, dark)
        text('Scale: 1 cm = 1 cm', x + pw / 2, foot * .18, .11, dark)
    bpy.ops.object.camera_add(location=(total_w / 2, total_h / 2, 10))
    cam = bpy.context.object
    cam.data.type = 'ORTHO'
    cam.data.ortho_scale = total_w
    cam.data.sensor_fit = 'HORIZONTAL'
    sc.camera = cam
    sc.render.filepath = os.path.join(out, 'sheet.png')
    bpy.ops.render.render(write_still=True)


try:
    sheet()
    sheet_file = 'sheet.png'
except Exception as error:  # برگه اختیاری است؛ پنج نما و مدل مهم‌ترند
    print('sheet failed:', error, file=sys.stderr)
    sheet_file = None

manifest = {
    'engine': 'Blender server renderer (Cycles)',
    'mode': 'pattern-sewn' if sewn.get('meshes') else 'parametric',
    'seam_error': sewn.get('seam_error'),
    'pieces': len(sewn.get('meshes', [])),
    'images': {k: k + '.png' for k in ('front', 'side', 'back', 'water', 'airflow')},
    'sheet': sheet_file,
    'model': 'garment.glb',
}
tmp = os.path.join(out, 'manifest.json.tmp')
with open(tmp, 'w', encoding='utf-8') as stream:
    json.dump(manifest, stream, ensure_ascii=False)
os.replace(tmp, os.path.join(out, 'manifest.json'))
