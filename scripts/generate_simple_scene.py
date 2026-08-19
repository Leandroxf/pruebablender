# Ejemplo: generar una escena simple y renderizar con Blender (usar con blender --background --python script.py)

import bpy
import os

# Ruta de salida: mismo directorio que el script
output_dir = os.path.join(os.path.dirname(__file__), "output")
os.makedirs(output_dir, exist_ok=True)
output_file = os.path.join(output_dir, "render.png")

# Limpiar escena (eliminar objetos existentes)
bpy.ops.object.select_all(action='SELECT')
bpy.ops.object.delete(use_global=False)

# Crear mesh: cubo
bpy.ops.mesh.primitive_cube_add(size=2, location=(0, 0, 1))
cube = bpy.context.active_object

# Crear cámara
cam_data = bpy.data.cameras.new('Camera')
cam = bpy.data.objects.new('Camera', cam_data)
bpy.context.collection.objects.link(cam)
cam.location = (5.0, -5.0, 3.0)
cam.rotation_euler = (1.1, 0, 0.78)
bpy.context.scene.camera = cam

# Crear luz
light_data = bpy.data.lights.new(name='Light', type='POINT')
light = bpy.data.objects.new(name='Light', object_data=light_data)
bpy.context.collection.objects.link(light)
light.location = (4.0, -4.0, 6.0)
light.data.energy = 1000

# Material para el cubo
mat = bpy.data.materials.new(name='CubeMat')
mat.use_nodes = True
bsdf = mat.node_tree.nodes.get('Principled BSDF')
if bsdf:
    bsdf.inputs['Base Color'].default_value = (0.2, 0.6, 0.9, 1)
cube.data.materials.append(mat)

# Render settings
scene = bpy.context.scene
scene.render.engine = 'CYCLES'  # o 'BLENDER_EEVEE'
scene.render.filepath = output_file
scene.render.image_settings.file_format = 'PNG'
scene.render.resolution_x = 1920
scene.render.resolution_y = 1080

# Si no hay GPU disponible, forzar CPU:
try:
    prefs = bpy.context.preferences
    cprefs = prefs.addons['cycles'].preferences
    cprefs.compute_device_type  # acceso para evitar excepción
except Exception:
    # No cambiar si no se puede acceder a preferencias en algunos entornos headless
    pass

# Renderizar y escribir archivo
bpy.ops.render.render(write_still=True)
print(f"Rendered to: {output_file}")
