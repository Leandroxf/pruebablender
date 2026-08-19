Scripts descargados desde GitHub (ejemplos CLI para Blender)

Origen principal: yuki-koyama/blender-cli-rendering (varios scripts para ejecutar con `blender --background --python <script>`)

Archivos incluidos aquí:
- 01_cube.py
- 02_suzanne.py
- 03_ibl.py
- 04_principled_bsdf.py
- 05_composition.py
- 06_split_tone.py
- 08_animation.py
- 09_armature.py
- 12_cloth.py
- 13_matcap.py

Notas:
- Estos scripts asumen la existencia de un módulo `utils` y recursos (assets/HDRIs, textures) en la misma carpeta. Para ejecutarlos directamente, añade los assets o adapta el script para no depender de ellos.
- Ejecutar ejemplo (Windows, usando setup_blender_portable y run_example.ps1):
  .\run_example.ps1 -BlenderExe "C:\Users\<tu_usuario>\Desktop\pruebablender\blender\blender-4.1.2\blender.exe" -Script "examples\02_suzanne.py -- <output.png> 100 128"

Licencia y atribución:
- Estos scripts son código público alojado en GitHub. Revisa las licencias en los repositorios originales antes de uso comercial o redistribución.
- Fuentes originales listadas en examples/SOURCES.md
