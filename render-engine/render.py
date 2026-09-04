import bpy, json, math, os, sys
from mathutils import Vector

job_path = sys.argv[sys.argv.index('--') + 1]
with open(job_path, encoding='utf-8') as stream:
    job = json.load(stream)
p = job.get('payload', {})
avatar = p.get('avatar', {})
garment = p.get('garment', {})
fabric = p.get('fabric', {})
out = '/data/app/public/renders/' + job['id']
os.makedirs(out, exist_ok=True)

bpy.ops.object.select_all(action='SELECT')
bpy.ops.object.delete(use_global=False)

def mat(name, color, rough=.55, metallic=0.0):
    m = bpy.data.materials.new(name)
    m.diffuse_color = (*color, 1)
    m.use_nodes = True
    bsdf = m.node_tree.nodes.get('Principled BSDF')
    bsdf.inputs['Base Color'].default_value = (*color, 1)
    bsdf.inputs['Roughness'].default_value = rough
    bsdf.inputs['Metallic'].default_value = metallic
    return m

def uv(name, location, scale, material):
    bpy.ops.mesh.primitive_uv_sphere_add(segments=48, ring_count=24, location=location)
    ob = bpy.context.object; ob.name = name; ob.scale = scale
    ob.data.materials.append(material); bpy.ops.object.shade_smooth(); return ob

def cylinder(name, a, b, radius, material):
    mid = (Vector(a) + Vector(b)) / 2; direction = Vector(b) - Vector(a)
    bpy.ops.mesh.primitive_cylinder_add(vertices=32, radius=radius, depth=direction.length, location=mid)
    ob = bpy.context.object; ob.name = name; ob.data.materials.append(material)
    ob.rotation_mode = 'QUATERNION'; ob.rotation_quaternion = direction.to_track_quat('Z', 'Y')
    bpy.ops.object.shade_smooth(); return ob

skin = mat('مانکن', (.72, .68, .61), .72)
cloth_hex = str(fabric.get('color', '#eeeae3')).lstrip('#')
try: cloth_rgb = tuple(int(cloth_hex[i:i+2], 16)/255 for i in (0,2,4))
except Exception: cloth_rgb = (.92,.9,.86)
cloth = mat('پارچه', cloth_rgb, max(.18, .68-float(fabric.get('sheen', .15))*.45))

height = max(1.45, min(1.9, float(avatar.get('height', 165))/100))
bust = float(avatar.get('bust', 90))/100
waist = float(avatar.get('waist', 72))/100
hip = float(avatar.get('hip', 98))/100
z_waist, z_bust, z_shoulder = height*.55, height*.69, height*.79
uv('Torso', (0,0,(z_waist+z_bust)/2), (bust*.27,bust*.18,(z_bust-z_waist)*.75), skin)
uv('Hips', (0,0,z_waist-.11), (hip*.26,hip*.18,.22), skin)
uv('Head', (0,0,height-.12), (.105,.09,.13), skin)
cylinder('Neck',(0,0,z_shoulder),(0,0,height-.23),.065,skin)
shoulder = float(avatar.get('shoulder_width', 38))/200
arm_len = float(avatar.get('arm_length', 58))/100
for side in (-1,1):
    cylinder('Arm', (side*shoulder,0,z_shoulder), (side*(shoulder+.05),0,z_shoulder-arm_len), .055, skin)
    cylinder('Leg', (side*.105,0,z_waist-.22), (side*.09,0,.12), .075, skin)

def garment_mesh(mode='dry'):
    lengths = garment.get('lengths', {})
    skirt_cm = float(lengths.get('skirt', 90) or 90)
    bottom = max(.04, z_waist-skirt_cm/100)
    silhouette = garment.get('silhouette', 'a_line')
    flare = {'fitted':1.03,'straight':1.12,'a_line':1.65,'flared':2.15}.get(silhouette,1.4)
    zs = [bottom + (z_shoulder-bottom)*i/22 for i in range(23)]
    verts=[]; faces=[]; n=72
    for ri,z in enumerate(zs):
        t=(z-bottom)/max(.01,z_shoulder-bottom)
        body_x = (waist*.27 if z<z_bust else bust*.27)
        skirt_t=max(0,min(1,(z_waist-z)/max(.01,z_waist-bottom)))
        rx=body_x*(1-skirt_t)+hip*.26*(1-skirt_t*.25)+hip*.26*flare*skirt_t
        ry=(waist*.19 if z<z_bust else bust*.19)*(1-skirt_t)+hip*.18*(1+skirt_t*.3)
        for i in range(n):
            a=2*math.pi*i/n
            fold=(.008+.018*skirt_t)*math.sin(a*9+ri*.28)
            x=(rx+fold)*math.cos(a); y=(ry+fold)*math.sin(a)
            if mode=='air':
                wind=skirt_t**2; x += .42*wind; y += .08*math.sin(a*3)*wind
            if mode=='water': z -= .035*skirt_t
            verts.append((x,y,z))
    for r in range(len(zs)-1):
        for i in range(n):
            j=(i+1)%n; a=r*n+i; b=r*n+j; c=(r+1)*n+j; d=(r+1)*n+i
            faces.append((a,b,c,d))
    mesh=bpy.data.meshes.new('لباس'); mesh.from_pydata(verts,[],faces); mesh.update()
    ob=bpy.data.objects.new('لباس',mesh); bpy.context.collection.objects.link(ob); ob.data.materials.append(cloth)
    sol=ob.modifiers.new('ضخامت','SOLIDIFY'); sol.thickness=.0025
    sub=ob.modifiers.new('نرمی','SUBSURF'); sub.levels=1; sub.render_levels=1
    for poly in mesh.polygons: poly.use_smooth=True
    return ob

garment_ob = garment_mesh('dry')
bpy.ops.mesh.primitive_plane_add(size=8, location=(0,0,0)); floor=bpy.context.object
floor.data.materials.append(mat('زمین',(.12,.12,.12),.8))
floor.hide_render = True

bpy.ops.object.light_add(type='AREA', location=(3,-4,4)); bpy.context.object.data.energy=950; bpy.context.object.data.shape='DISK'; bpy.context.object.data.size=4
bpy.ops.object.light_add(type='AREA', location=(-3,-1,3)); bpy.context.object.data.energy=650; bpy.context.object.data.size=3
bpy.ops.object.light_add(type='AREA', location=(0,3,4)); bpy.context.object.data.energy=800; bpy.context.object.data.size=2

scene=bpy.context.scene
try:
    scene.render.engine = 'BLENDER_EEVEE_NEXT'
except TypeError:
    scene.render.engine = 'BLENDER_EEVEE'
scene.render.resolution_x=720; scene.render.resolution_y=900; scene.render.resolution_percentage=100
scene.render.image_settings.file_format='PNG'; scene.render.film_transparent=False
scene.world.color=(.14,.14,.14)
bpy.ops.object.camera_add(); camera=bpy.context.object; scene.camera=camera

def shot(name, pos, mode='dry'):
    global garment_ob
    if mode!='dry':
        bpy.data.objects.remove(garment_ob, do_unlink=True); garment_ob=garment_mesh(mode)
    camera.location=pos; target=Vector((0,0,height*.5)); camera.rotation_euler=(target-Vector(pos)).to_track_quat('-Z','Y').to_euler(); camera.data.lens=62
    scene.render.filepath=os.path.join(out,name+'.png'); bpy.ops.render.render(write_still=True)
    if mode!='dry':
        bpy.data.objects.remove(garment_ob, do_unlink=True); garment_ob=garment_mesh('dry')

shot('front',(0,-3.25,height*.58)); shot('side',(3.25,0,height*.58)); shot('back',(0,3.25,height*.58))
shot('water',(0,-3.25,height*.58),'water'); shot('airflow',(0,-3.25,height*.58),'air')
scene.render.filepath=os.path.join(out,'garment.glb')
bpy.ops.export_scene.gltf(filepath=scene.render.filepath, export_format='GLB', use_selection=False)
manifest={'engine':'Blender server renderer','images':{k:k+'.png' for k in ('front','side','back','water','airflow')},'model':'garment.glb'}
tmp=os.path.join(out,'manifest.json.tmp')
with open(tmp,'w',encoding='utf-8') as stream: json.dump(manifest,stream,ensure_ascii=False)
os.replace(tmp,os.path.join(out,'manifest.json'))

