# generate_head_block.py
# Script de ejemplo: bloquea la forma de una cabeza simple (cráneo, mandíbula, cuencas oculares)
# Ejecutar con: blender --background --python scripts/generate_head_block.py

import bpy
import os
from mathutils import Vector

output_dir = os.path.join(os.path.dirname(__file__), "output", "head")
os.makedirs(output_dir, exist_ok=True)
output_file = os.path.join(output_dir, "head.obj")

# Limpiar escena
bpy.ops.object.select_all(action='SELECT')
bpy.ops.object.delete(use_global=False)

# Cráneo
bpy.ops.mesh.primitive_uv_sphere_add(radius=1.0, location=(0, 0, 1.0))
skull = bpy.context.active_object
skull.name = 'Skull'

# Aplanar ligeramente la cara
bpy.ops.object.mode_set(mode='EDIT')
import bmesh
bm = bmesh.from_edit_mesh(skull.data)
for v in bm.verts:
    if v.co.y > 0.2:
        v.co.y *= 0.8
bmesh.update_edit_mesh(skull.data)
bpy.ops.object.mode_set(mode='OBJECT')

# Mandíbula: esfera escalada y desplazada
bpy.ops.mesh.primitive_uv_sphere_add(radius=0.6, location=(0, -0.6, 0.3))
jaw = bpy.context.active_object
jaw.scale = (1.0, 1.2, 0.6)
jaw.name = 'Jaw'

# Cuencas oculares: boolean (solo aproximación)
bpy.ops.mesh.primitive_uv_sphere_add(radius=0.25, location=(0.4, 0.6, 1.1))
eyeL = bpy.context.active_object
bpy.ops.mesh.primitive_uv_sphere_add(radius=0.25, location=(-0.4, 0.6, 1.1))
eyeR = bpy.context.active_object

# Parent
jaw.parent = skull
eyeL.parent = skull
eyeR.parent = skull

# Suavizar
for obj in bpy.context.scene.objects:
    if obj.type == 'MESH':
        for f in obj.data.polygons:
            f.use_smooth = True

# Export
bpy.ops.export_scene.obj(filepath=output_file, use_selection=False, axis_forward='-Z', axis_up='Y')
print('Head block exported to:', output_file)
