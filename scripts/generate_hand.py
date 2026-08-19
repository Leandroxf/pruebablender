# generate_hand.py
# Script de ejemplo: genera una mano simplificada usando cilindros para dedos y una esfera para la palma.
# Ejecutar con: blender --background --python scripts/generate_hand.py

import bpy
import os
from mathutils import Vector

output_dir = os.path.join(os.path.dirname(__file__), "output", "hand")
os.makedirs(output_dir, exist_ok=True)
output_file = os.path.join(output_dir, "hand.obj")

# Limpiar escena
bpy.ops.object.select_all(action='SELECT')
bpy.ops.object.delete(use_global=False)

# Crear palma (metaball o UV sphere)
bpy.ops.mesh.primitive_uv_sphere_add(radius=0.6, location=(0, 0, 0.2))
palm = bpy.context.active_object
palm.name = 'Palm'

# Crear dedos: 4 dedos y pulgar
finger_length = 0.9
finger_radius = 0.12
finger_offsets = [(-0.45, 0.2, 0.6), (-0.15, 0.25, 0.7), (0.15, 0.25, 0.7), (0.45, 0.2, 0.6)]
for i, off in enumerate(finger_offsets):
    bpy.ops.mesh.primitive_cylinder_add(radius=finger_radius, depth=finger_length, location=off)
    f = bpy.context.active_object
    f.rotation_euler = (1.57, 0, 0)
    f.location = Vector(off) + Vector((0, 0, finger_length/2))
    f.name = f'Finger_{i+1}'

# Pulgar
bpy.ops.mesh.primitive_cylinder_add(radius=0.13, depth=0.8, location=(-0.6, -0.1, 0.5))
thumb = bpy.context.active_object
thumb.rotation_euler = (1.0, 0, -0.7)
thumb.name = 'Thumb'

# Unión simple: parent dedos a palma para paquete
for obj in bpy.context.scene.objects:
    if obj.name.startswith('Finger') or obj.name == 'Thumb':
        obj.parent = palm

# Opcional: suavizar
for obj in bpy.context.scene.objects:
    if obj.type == 'MESH':
        for face in obj.data.polygons:
            face.use_smooth = True

# Exportar como OBJ para referencia/retopología
bpy.ops.export_scene.obj(filepath=output_file, use_selection=False, axis_forward='-Z', axis_up='Y')
print('Hand exported to:', output_file)
