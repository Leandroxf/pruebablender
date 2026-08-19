Preparación y uso (SETUP)

Objetivo
- Preparar el repositorio local para usar Blender más tarde sin instalar nada ahora.

Pasos recomendados cuando tengas Blender disponible:
1) Descargar y extraer una versión portable (recomendado):
   - Abrir PowerShell y ejecutar (desde scripts):
     .\setup_blender_portable.ps1 -Version 4.1.2 -Destination "$env:USERPROFILE\Desktop\pruebablender\blender"
   - Esto descarga y extrae Blender en la carpeta indicada.

2) Ejecutar un ejemplo:
   - Usar run_example.ps1 indicando la ruta al blender.exe extraído, por ejemplo:
     .\run_example.ps1 -BlenderExe "$env:USERPROFILE\Desktop\pruebablender\blender\blender-4.1.2\blender.exe" -Script "scripts\generate_hand.py"
   - Salidas y renders se escribirán en scripts\output\*

Notas
- Los scripts Python incluidos usan la API bpy y deben ejecutarse con el Python embebido de Blender (no con el Python del sistema).
- No se realizan cambios en el sistema ni se modifica PATH global.
- Para uso en CI/agents, es mejor instalar Blender en el runner o usar runners que ya lo incluyan.
