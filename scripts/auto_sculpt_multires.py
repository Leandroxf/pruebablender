# auto_sculpt_multires.py
# Script de ejemplo: crear base, subdividir y aplicar desplazamiento procedural para simular un "sculpt" automático.
# Ejecutar con: blender --background --python scripts/auto_sculpt_multires.py

import bpy
import os
from mathutils import Vector
import datetime

# Salida
out_root = os.path.join(os.path.dirname(__file__), "output", "sculpt")
timestamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
out_dir = os.path.join(out_root, timestamp)
os.makedirs(out_dir, exist_ok=True)
out_obj = os.path.join(out_dir, "auto_sculpt.obj")
out_blend = os.path.join(out_dir, "auto_sculpt.blend")

# Limpiar escena
bpy.ops.object.select_all(action='SELECT')
try:
    bpy.ops.object.delete(use_global=False)
except Exception:
    pass

# Crear base: esfera UV
bpy.ops.mesh.primitive_uv_sphere_add(radius=1.0, location=(0, 0, 0))
obj = bpy.context.active_object
obj.name = 'SculptBase'

# Esculpir forma básica (no interactivo): escalar zonas para sugerir rostro
# Mover vértices de ejemplo (simple) para crear una 'cara' aproximada
bpy.ops.object.mode_set(mode='EDIT')
import bmesh
bm = bmesh.from_edit_mesh(obj.data)
for v in bm.verts:
    # empujar la parte frontal (y positivo) ligeramente para sugerir cara
    if v.co.y > 0.0:
        v.co.y += 0.05 * (v.co.y)
bmesh.update_edit_mesh(obj.data)
bpy.ops.object.mode_set(mode='OBJECT')

# Añadir Subdivision Surface para aumentar resolución
sub = obj.modifiers.new(name='Subsurf', type='SUBSURF')
sub.levels = 2
sub.render_levels = 3
sub.subdivision_type = 'CATMULL_CLARK'

# Añadir Displace con textura procedural (Clouds)
tex = bpy.data.textures.new('SculptNoise', type='CLOUDS')
tex.noise_scale = 0.5

disp = obj.modifiers.new(name='Displace', type='DISPLACE')
disp.texture = tex
disp.strength = 0.15

disp.mid_level = 0.0

# Opcional: añadir otro Displace más fino para detalle
tex2 = bpy.data.textures.new('SculptNoiseFine', type='CLOUDS')
tex2.noise_scale = 0.08

disp2 = obj.modifiers.new(name='DisplaceFine', type='DISPLACE')
disp2.texture = tex2
disp2.strength = 0.02

disp2.mid_level = 0.0

# Aplicar modifiers para fijar malla de alto detalle
# Primero aplicar Displace fine, luego Displace, luego Subsurf
try:
    bpy.context.view_layer.objects.active = obj
    bpy.ops.object.modifier_apply(modifier=disp2.name)
    bpy.ops.object.modifier_apply(modifier=disp.name)
    bpy.ops.object.modifier_apply(modifier=sub.name)
except Exception as e:
    print('Advertencia al aplicar modifiers (puede necesitar UI):', e)

# Suavizar shading
if obj.type == 'MESH':
    for f in obj.data.polygons:
        f.use_smooth = True

# Añadir cámara y luz para preview
cam_data = bpy.data.cameras.new('Camera')
cam = bpy.data.objects.new('Camera', cam_data)
bpy.context.collection.objects.link(cam)
cam.location = (5.0, -5.0, 3.0)
cam.rotation_euler = (1.1, 0, 0.78)
bpy.context.scene.camera = cam

light_data = bpy.data.lights.new(name='Light', type='POINT')
light = bpy.data.objects.new(name='Light', object_data=light_data)
bpy.context.collection.objects.link(light)
light.location = (4.0, -4.0, 6.0)
light.data.energy = 1000

# Guardar OBJ y .blend
try:
    bpy.ops.export_scene.obj(filepath=out_obj, use_selection=False, axis_forward='-Z', axis_up='Y')
    bpy.ops.wm.save_as_mainfile(filepath=out_blend)
    print('Sculpt auto generado en:', out_dir)
except Exception as e:
    print('Fallo al exportar:', e)

print('Fin auto_sculpt_multires.py')
