# generate_cloth_sim.py
# Script de ejemplo: crea un plano de tela que cae sobre una esfera y bakea la simulación.
# Ejecutar con: blender --background --python scripts/generate_cloth_sim.py

import bpy
import os

output_dir = os.path.join(os.path.dirname(__file__), "output", "cloth")
os.makedirs(output_dir, exist_ok=True)
output_file = os.path.join(output_dir, "cloth_frame_50.png")

# Limpiar escena
bpy.ops.object.select_all(action='SELECT')
bpy.ops.object.delete(use_global=False)

# Crear objeto collider (esfera)
bpy.ops.mesh.primitive_uv_sphere_add(radius=1.0, location=(0, 0, 1.0))
coll = bpy.context.active_object
coll.name = 'Collider'

# Crear plano de cloth
bpy.ops.mesh.primitive_plane_add(size=3, location=(0, 0, 3.5))
cloth = bpy.context.active_object
cloth.name = 'Cloth'

# Subdividir el plano para más resolución
bpy.ops.object.mode_set(mode='EDIT')
import bmesh
bm = bmesh.from_edit_mesh(cloth.data)
bmesh.ops.subdivide_edges(bm, edges=bm.edges, cuts=40, use_grid_fill=True)
bmesh.update_edit_mesh(cloth.data)
bpy.ops.object.mode_set(mode='OBJECT')

# Añadir modificador cloth
cloth_mod = cloth.modifiers.new('ClothSim', type='CLOTH')
cloth_mod.settings.quality = 5
cloth_mod.settings.mass = 0.3

# Habilitar colisiones en collider y cloth
coll_mod = coll.modifiers.new('Collision', type='COLLISION')

# Bake the simulation to a point cache
bpy.context.scene.frame_end = 50
bpy.ops.ptcache.free_bake_all()
try:
    bpy.ops.ptcache.bake_all(bake=True)
except Exception as e:
    print('Bake failed or not supported in headless runner:', e)

# Set camera and light for render
cam_data = bpy.data.cameras.new('Camera')
cam = bpy.data.objects.new('Camera', cam_data)
bpy.context.collection.objects.link(cam)
cam.location = (6, -6, 4)
cam.rotation_euler = (1.1, 0, 0.8)
bpy.context.scene.camera = cam

light_data = bpy.data.lights.new(name='KeyLight', type='AREA')
light = bpy.data.objects.new(name='KeyLight', object_data=light_data)
bpy.context.collection.objects.link(light)
light.location = (4, -4, 6)

# Render frame 50
scene = bpy.context.scene
scene.render.engine = 'BLENDER_EEVEE'
scene.frame_set(50)
scene.render.filepath = output_file
scene.render.image_settings.file_format = 'PNG'
scene.render.resolution_x = 1280
scene.render.resolution_y = 720

try:
    bpy.ops.render.render(write_still=True)
    print('Cloth render saved to:', output_file)
except Exception as e:
    print('Render failed:', e)
