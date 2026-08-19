# PruebaBlender — Agente de conocimiento de Blender

Este repositorio local contiene documentación y ejemplos que sirven como "conocimiento" para el agente que automatiza la creación y renderizado con Blender.

Contenido:
- docs/BLENDER_KNOWLEDGE.md — Guía en español sobre Blender y su uso.
- scripts/generate_simple_scene.py — Ejemplo de script en Python (bpy) para generar una escena y renderizar desde la línea de comandos.

Cómo ejecutar el ejemplo (local con Blender instalado):

1. Abrir terminal/powershell.
2. Ejecutar:
   "C:\path\to\blender.exe" --background --python scripts\generate_simple_scene.py

Nota: bpy (la API de Blender) solo está disponible dentro de la Python embebida de Blender. No intentes ejecutar el script con el Python del sistema sin Blender.
