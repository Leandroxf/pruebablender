# 🎬 Blender Copilot Agent - Guía Completa (ES)

> **¡Bienvenido!** Esta es tu guía para usar el agente Blender. **Solo necesitas ejecutar UN script.**

## 📋 Tabla de Contenidos

- [Instalación Rápida (Un Clic)](#-instalación-rápida-un-clic)
- [¿Cómo Funciona?](#-cómo-funciona)
- [Diagrama de Flujo](#-diagrama-de-flujo)
- [Ejemplos de Uso](#-ejemplos-de-uso)
- [Troubleshooting](#-troubleshooting)
- [FAQ](#-faq)

---

## 🚀 Instalación Rápida (UN CLIC)

### **El Único Comando que Necesitas**

```powershell
# 1. Abre PowerShell como Administrador (Win + X → A)

# 2. Navega a la carpeta del agente
cd C:\ruta\a\blender-copilot-agent

# 3. Ejecuta el setup (una sola vez)
.\scripts\setup_blender_portable.ps1
```

**¡Eso es TODO!** El script hace automáticamente:

✅ **Descarga Blender** (con reintentos automáticos)  
✅ **Valida espacio disco** (5GB mínimo)  
✅ **Extrae archivos** (con barra de progreso)  
✅ **Verifica dependencias** (health check integrado)  
❓ **Pregunta**: ¿Ejecutar agente ahora?  
✅ **Ejecuta automáticamente** si dices SÍ  
📊 **Muestra resumen** con hints si hay problemas

---

## 🔄 ¿Cómo Funciona?

### **Estructura de Fases**

```
┌─────────────────────────────────────────────────────────────┐
│                    FASE 1: INSTALACIÓN                      │
├─────────────────────────────────────────────────────────────┤
│ • Valida espacio en disco (5GB mín.)                        │
│ • Descarga Blender (con reintentos)                         │
│ • Extrae archivos (progreso visual)                         │
│ • Valida instalación                                        │
│                                                             │
│                FASE 2: VALIDACIÓN DE SALUD                 │
├─────────────────────────────────────────────────────────────┤
│ ✓ Permisos de administrador                                │
│ ✓ Versión de PowerShell                                     │
│ ✓ Instalación de Blender                                    │
│ ✓ Ambiente Python                                           │
│ ✓ Directorio de scripts                                     │
│ ✓ Espacio en disco                                          │
│ ✓ Conectividad de red                                       │
│                                                             │
│            FASE 3: EJECUCIÓN (OPCIONAL)                    │
├─────────────────────────────────────────────────────────────┤
│ ❓ ¿Ejecutar agente ahora? (SÍ/NO)                          │
│    SI → Ejecuta run_agent_wrapper.ps1                       │
│    NO → Termina gracefully                                  │
│                                                             │
│               FASE 4: RESUMEN & PRÓXIMOS PASOS             │
├─────────────────────────────────────────────────────────────┤
│ 📊 Estado de cada fase                                       │
│ 💡 Hints si hay problemas                                    │
│ ✨ Directorio de Blender                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## 📊 Diagrama de Flujo

```
START
  │
  ▼
┌─────────────────────────────────┐
│ setup_blender_portable.ps1      │
│ (El único script que ejecutas)  │
└────────────┬────────────────────┘
             │
             ▼
    ┌────────────────────────────────────────┐
    │ FASE 1: INSTALAR BLENDER              │
    │ • Descargar                           │
    │ • Extraer (con progreso)              │
    │ • Validar                             │
    └────────────┬─────────────────────────┘
                 │
           ÉXITO?│
         ┌───────┴──────┐
         │              │
         NO             YES
         │              │
         ▼              ▼
      (ERROR)    ┌────────────────────────────────────────┐
         │       │ FASE 2: VALIDAR DEPENDENCIAS         │
         │       │ • Admin? Python? Espacio? Scripts?   │
         │       │ • Mostrar hints si faltan cosas      │
         │       └────────────┬─────────────────────────┘
         │                    │
         │                    ▼
         │       ¿Ejecutar agente?
         │            ❓ (Pregunta)
         │        ┌────────┴──────────┐
         │        │                   │
         │        SÍ                  NO
         │        │                   │
         │        ▼                   ▼
         │   (Ejecuta agente)  (Termina)
         │        │                   │
         └────────┴───────┬──────────┘
                         │
                         ▼
         ┌──────────────────────────────────┐
         │ FASE 4: RESUMEN FINAL           │
         │ • Estado de ejecución           │
         │ • Ubicación de Blender          │
         │ • Próximos pasos                │
         └────────────┬─────────────────────┘
                      │
                      ▼
                    END
                    ✅ LISTO
```

---

## 📖 Ejemplos de Uso

### **Ejemplo 1: Setup Completo (Primera Vez)**

```powershell
# 1. Abrir PowerShell como Administrador
#    Presiona: Win + X → A

# 2. Ir a la carpeta del proyecto
cd C:\Users\Leandro\Desktop\pruebablender

# 3. Permitir ejecución de scripts (una sola vez)
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
# Escribe: Y (para confirmar)

# 4. Ejecutar el setup
.\scripts\setup_blender_portable.ps1

# 5. Ver el progreso
#    [████████░░░░░░░░░░░░] 65% - Extracting
#
#    ✓ PASS Blender Installation - 3/3 components present
#    ✓ PASS Python Environment - Python 3.10.2
#    ...
#
#    🤖 Would you like to run the Blender agent now? (S/N): s
#    
#    [Ejecutando agente...]
#    ✓ COMPLETED

# ✅ ¡Listo!
```

### **Ejemplo 2: Setup Personalizado**

```powershell
# Cambiar ubicación de Blender
.\scripts\setup_blender_portable.ps1 `
    -BlenderVersion "4.1.0" `
    -BlenderPath "D:\Blender" `
    -MaxRetries 5

# Parámetros disponibles:
# -BlenderVersion : Versión de Blender (default: 4.0.1)
# -BlenderPath    : Dónde instalar (default: C:\blender_portable)
# -ScriptsDir     : Carpeta con scripts (default: ./agents)
# -ConfigFile     : Archivo de config (default: ./config.json)
# -MaxRetries     : Reintentos descarga (default: 3)
# -Architecture   : x64 o x86 (default: x64)
```

### **Ejemplo 3: Automatizar Ejecución Diaria**

```powershell
# Crear tarea programada en Windows Task Scheduler

# 1. Crear un archivo batch (run_agent.bat)
# Contenido:
# @echo off
# cd /d "C:\Users\Leandro\Desktop\pruebablender"
# powershell.exe -NoProfile -ExecutionPolicy Bypass -File "scripts\setup_blender_portable.ps1"

# 2. Crear tarea en PowerShell
$action = New-ScheduledTaskAction -Execute "C:\Users\Leandro\Desktop\pruebablender\run_agent.bat"
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -RunLevel Highest

Register-ScheduledTask -TaskName "BlenderAgentDaily" `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal

# 3. Verificar que se creó
Get-ScheduledTask -TaskName "BlenderAgentDaily"
```

---

## 🆘 Troubleshooting

### **Error: "File cannot be loaded because running scripts is disabled"**

**Solución:**
```powershell
# Abre PowerShell como Admin y ejecuta:
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

# Confirma escribiendo: Y
```

---

### **Error: "Blender executable not found"**

**Solución:**
```powershell
# El script falla si Blender no se descargó correctamente
# Intenta nuevamente con más reintentos:

.\scripts\setup_blender_portable.ps1 -MaxRetries 5

# Si sigue fallando, verifica manualmente:
# 1. ¿Tienes conexión a Internet?
# 2. ¿Tienes al menos 5 GB libres?
# 3. ¿Se descargó el archivo ZIP en C:\Users\...\AppData\Local\Temp?

# Si todo está bien pero falla, descarga manualmente:
# https://download.blender.org/release/
```

---

### **Error: "Insufficient disk space"**

**Solución:**
```powershell
# El setup necesita 5 GB mínimo

# 1. Libera espacio:
#    • Elimina archivos antiguos
#    • Vacía papelera
#    • Desinstala programas no usados

# 2. Intenta nuevamente:
.\scripts\setup_blender_portable.ps1

# 3. O cambia la ubicación:
.\scripts\setup_blender_portable.ps1 -BlenderPath "D:\blender_portable"
#                                      (si D: tiene más espacio)
```

---

### **Error: "Download failed after 3 attempts"**

**Solución:**
```powershell
# Reintentos automáticos fallaron. Intenta:

# 1. Aumentar reintentos:
.\scripts\setup_blender_portable.ps1 -MaxRetries 7 -RetryDelaySeconds 10

# 2. Verificar conexión:
Test-NetConnection -ComputerName google.com -Port 443

# 3. Si estás detrás de proxy:
[System.Net.ServicePointManager]::DefaultProxy = New-Object System.Net.WebProxy("http://proxy:puerto")
.\scripts\setup_blender_portable.ps1

# 4. Descarga manual desde:
# https://download.blender.org/release/
```

---

### **Error: "Not running as Administrator"**

**Solución:**
```powershell
# El script necesita permisos de admin

# Opción 1: Abrir PowerShell como Admin
# Presiona: Win + X → A

# Opción 2: Ejecutar desde PowerShell actual
Start-Process powershell -Verb RunAs -ArgumentList "-NoExit -Command cd '$PWD'; .\scripts\setup_blender_portable.ps1"
```

---

## ❓ FAQ

### **P: ¿Necesito ser Administrador?**
**R:** Recomendado. Algunos scripts requieren permisos de admin para crear directorios y acceder a recursos del sistema. Abre PowerShell como Admin para evitar errores.

---

### **P: ¿Cuánto espacio necesita Blender?**
**R:** Mínimo **5 GB**. Blender portable + archivos adicionales necesitan ese espacio. Ten al menos 10 GB disponibles para margen de seguridad.

---

### **P: ¿Puedo usar Blender en modo offline?**
**R:** 
- **Instalación:** Necesitas Internet para descargar Blender
- **Ejecución:** Puedes ejecutar scripts en modo offline
- **Updates:** Si quieres actualizar Blender, necesitas Internet

---

### **P: ¿Cómo programo el agente para ejecutarse automáticamente?**
**R:** Usa Windows Task Scheduler (ver sección "Ejemplo 3").

---

### **P: ¿Dónde quedan los logs?**
**R:** En la carpeta del agente:
- `blender_setup_YYYYMMDD_HHMMSS.log` - Setup logs
- `agent_execution_YYYYMMDD_HHMMSS.log` - Ejecución logs
- `agent_metrics_YYYYMMDD_HHMMSS.json` - Métricas

---

### **P: ¿Qué pasa si falla un script?**
**R:** El wrapper captura el error, lo registra, y continúa con el siguiente script. Al final muestra un resumen con errores.

---

### **P: ¿Puedo cambiar la versión de Blender?**
**R:** Sí, usa el parámetro `-BlenderVersion`:
```powershell
.\scripts\setup_blender_portable.ps1 -BlenderVersion "4.1.0"
```

---

### **P: ¿Qué son los "scripts del agente"?**
**R:** Son archivos Python (.py) que Blender ejecuta. Colócalos en la carpeta `./agents` (o la que especifiques con `-ScriptsDir`).

---

## ✨ Características del Setup

✅ Descargar Blender automáticamente  
✅ Reintentos automáticos en descargas fallidas  
✅ Validación de espacio en disco (5 GB mín.)  
✅ Barra de progreso visual  
✅ Extracción con progreso  
✅ Validación post-instalación  
✅ Health check integrado  
✅ Preguntas interactivas  
✅ Ejecución automática opcional  
✅ Logging detallado con timestamps  
✅ Hints actionables para problemas  
✅ Resumen final con próximos pasos  

---

## 📞 Soporte

Si tienes problemas:

1. ✅ Lee esta guía
2. 📖 Revisa la sección "Troubleshooting"
3. 📋 Verifica los logs (.log files)
4. 💡 Busca la respuesta en "FAQ"
5. 📧 Contacta al equipo de desarrollo

---

**Última actualización:** 2024-01-15  
**Versión de Blender:** 4.0.1  
**Autor:** Blender Copilot Team  
