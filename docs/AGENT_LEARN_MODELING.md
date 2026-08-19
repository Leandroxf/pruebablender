AGENT: Aprendizaje automático de modelado (instrucciones)

Objetivo
El agente debe intentar aprender a modelar mediante ejecución automática de ejemplos, análisis de resultados y pequeñas iteraciones de parámetros cuando Blender esté disponible.

Prerequisitos
- Blender instalado en el runner o usar scripts/setup_blender_portable.ps1 para descargarlo.
- Scripts de examples/ y scripts/ presentes (ya incluidos).

Flujo de trabajo recomendado para el agente
1) Preparación
   - Ejecutar scripts de examples/ con: "<blender.exe> --background --python examples/<script>.py -- <output> <res%> <samples>"
   - Guardar resultados en examples/output/<script>/ con timestamps.
2) Evaluación automática
   - Para cada output (imagen/OBJ/.blend): calcular métricas simples (resolución, contraste, silueta match vs referencia si existe, conteo de vértices para OBJs).
   - Registrar errores/advertencias (falta de assets, excepciones de bpy).
3) Aprendizaje iterativo
   - Ajustar parámetros de ejecución (resolución, samples, subdiv levels, cloth quality) y re-ejecutar para ver impacto.
   - Registrar una línea por experimento en docs/agent_logs/experiments.log con: script, params, métricas, ruta output, nota.
4) Reporte y recomendaciones
   - Generar resúmenes periódicos (docs/agent_logs/summary.md) con mejores parámetros y fallos frecuentes.

Estructura de archivos que crea el agente
- docs/agent_logs/experiments.log  (registro CSV/TSV)
- docs/agent_logs/summary.md
- examples/output/<script>/ (resultados por script)

Criterios de éxito
- Capacidad de ejecutar ejemplos sin errores.
- Mejora reproducible en métricas (p. ej. menor ruido tras ajustar samples; mejor cobertura de silueta tras cambiar parámetros de posición).

Limitaciones
- El agente no modificará la red ni instalará Blender sin permiso del usuario; solo prepara, ejecuta y registra cuando se le indique la ruta al binario.
- No intenta remplazar juicio humano para sculpt avanzado; provee parámetros y resultados para revisión.

Comandos útiles
- Ejecutar un script: scripts\run_example.ps1 -BlenderExe "C:\path\to\blender.exe" -Script "examples\02_suzanne.py -- output.png 100 128"
- Listar experiments: Get-Content docs\agent_logs\experiments.log | Select-Object -Last 20

Fecha: 2026-08-18
