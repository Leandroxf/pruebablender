BLENDER — Guía y conocimiento esencial (resumen en español)

1) ¿Qué es Blender?
Blender es una suite 3D libre y de código abierto para modelado, esculpido, texturizado, animación, simulaciones (física, partículas), renderizado (Cycles, Eevee), composición y edición de vídeo.

2) Instalación
- Windows: descargar desde https://www.blender.org/download/ y usar el instalador o la versión portable.
- Linux/macOS: usar paquetes oficiales o gestores de paquetes.

3) Interfaz y conceptos clave
- Editor types: 3D Viewport, Shader Editor, UV Editor, Timeline, Dope Sheet, Graph Editor.
- Objetos vs. Malla (Object Mode / Edit Mode).
- Modificadores (Subdivision Surface, Boolean, Mirror, Array) para modelado no destructivo.
- Materiales: nodos en Shader Editor; Principled BSDF como punto de partida.
- Texturizado: UV Unwrap, Paint, bake.

4) Modelado común
- Box modeling, edge loops, extrude, inset, bevel.
- Usar Snap (vertex/face) y proportional editing para formas orgánicas.

5) Sombreado y materiales
- Cycles: trazado de rayos, más preciso.
- Eevee: render en tiempo real, rápido.
- Nodos: combinar texturas, colores, normales y roughness.

6) Iluminación y cámaras
- Tipos de luces: Point, Sun, Spot, Area.
- Configurar exposición y colorimetría en Render Properties.
- Colocar cámara y usar "View -> Align Active Camera to View".

7) Animación
- Keyframes, Graph Editor, Dope Sheet.
- Rigging: Armatures, Weight Painting, Constraints.

8) Simulaciones
- Rigid Body, Cloth, Fluid, Smoke/Volumetrics, Particles.

9) Scripting: Python y bpy
- La API `bpy` permite crear/editar escenas, materiales, animaciones y lanzar renders.
- Ejecutar scripts: `blender --background --python script.py` para ejecución headless.
- Desde dentro de Blender: Text Editor -> Run Script.

10) Ejecución headless y render en CI
- Usar `--background` para no abrir UI y `--render-output`/`--render-frame` o `bpy.ops.render.render(write_still=True)` en script.
- Atiende a licencias y dependencias (addons privados no disponibles en runners públicos).

11) Buenas prácticas para automatización
- Emplear rutas absolutas en renders y usar `os.path` para outputs.
- Limpiar escena antes de crear objetos en scripts.
- Evitar operaciones que requieran GPU si el runner no dispone de ella; usar CPU render en Cycles o Eevee según disponibilidad.

12) Recursos útiles
- Documentación oficial: https://docs.blender.org
- API Python: https://docs.blender.org/api/current/
- Tutoriales: Blender Fundamentals en blender.org, Blender Stack Exchange.


Ejecutar scripts desde CI o agentes: siempre proveer el binario de Blender en el runner (o usar runners con Blender preinstalado). Asegurar que `bpy` se carga desde la Python de ese binario.
