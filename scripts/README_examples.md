Ejemplos de scripts para modelado y simulación (usar con Blender)

Archivos:
- generate_hand.py — genera una mano simplificada y exporta OBJ en scripts/output/hand
- generate_head_block.py — bloquea la forma de una cabeza y exporta OBJ en scripts/output/head
- generate_cloth_sim.py — crea un plano de tela que cae sobre una esfera y renderiza el frame 50 en scripts/output/cloth

Cómo ejecutar:
1. Instalar/usar Blender local.
2. Desde la terminal: "C:\ruta\a\blender.exe --background --python scripts\generate_hand.py"
3. Revisar los archivos generados en scripts\output\<tipo>

Notas:
- Los scripts son ejemplos educativos; requieren la versión de Blender que incluya bpy y soporte modifiers/cloth.
- Para detalles finales y retopología, importar los OBJ en Blender y refinar manualmente.
