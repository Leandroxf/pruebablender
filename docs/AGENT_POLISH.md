AGENT POLISH — Lista de mejoras y checklist

Objetivo
Pulir el agente para que sea robusto, portable y fácil de operar por terceros.

Mejoras incluidas (resumen)
- Scripts v1..v4 para ejecución con distintas opciones de robustez.
- Descarga automática de utils.py y Node portable (third_party).
- Logging: experiments.log (TSV), experiments.json (NDJSON), result.json por experimento, summary.md.
- Métricas de imagen y compresión de outputs.
- Herramientas de control: run_agent_wrapper.ps1, health_check.ps1.

Checklist de despliegue
1. Copiar carpeta pruebablender completa al equipo destino.
2. Ejecutar scripts\health_check.ps1 y resolver mensajes.
3. (Opcional) Ejecutar scripts\download_utils.ps1 para obtener utils.py
4. Ejecutar scripts\run_agent_wrapper.ps1 -Version v3 -DryRun para verificar comandos.
5. Ejecutar sin -DryRun cuando estés listo.

Seguridad y permisos
- No se suben credenciales. Configs sensibles deben ir en secretos (webhook, tokens).
- El registro de experimentos es local por defecto (docs/agent_logs). No subir logs a repos remotos si contienen datos sensibles.

Extensiones sugeridas
- Soporte GPU detection y selección de render engine.
- Plugins para subir automáticamente resultados a storage o bucket.

Fecha: 2026-08-18
