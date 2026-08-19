Modelado anatómico y detalles — Guía práctica (español)

Resumen

Esta guía recoge técnicas, flujo de trabajo y consejos para modelar partes del cuerpo humano con detalle: cabeza, manos, ojos, boca, orejas, torso y extremidades. Incluye pasos desde el bloqueo hasta el detalle final (sculpt, retopología, mapas de detalle) y recomendaciones para animación y render.

1. Principios generales
- Referencias: usar fotos en vistas frontal/ lateral/3/4 y siluetas. Coleccionar referencias de proporciones y músculos.
- Proporciones básicas: aprende las reglas de proporción (por ejemplo, la cabeza mide ~1/8 de la altura total) y adáptalas al estilo.
- Bloqueo primero: empezar con formas simples (cajas/ esferas) para establecer volúmenes y silueta.
- Trabajar de lo general a lo particular: formas grandes → anatomía → pliegues y detalles superficiales.

2. Cabeza y rostro
- Bloqueo: usar esfera para cráneo, extruir mandíbula; marcar plano facial.
- Ojos: modelar cuenca primero; mantener edge loops circulares para deformación. Crear globo ocular separado.
- Nariz: construir puente y alas como volumen; evitar polígonos planos; usar edge loops que definan las alas y el tabique.
- Boca: loops alrededor de la boca (orbicularis oris) para permitir animación; añadir soporte en labios (anillo de polígonos).
- Orejas: empezar con una base plana, luego extraer y esculpir el helix, antihelix y trago; usar referencias de silueta.

3. Manos y dedos
- Bloqueo: formas cilíndricas para dedos y palma; definir volúmenes de nudillos y almohadillas.
- Topología: bucles en articulaciones (nudillos) para buen doblado; polígonos ligeramente cuadrados.
- Uñas: pueden ser géneros separados o extrusiones de la malla de la falange.
- Separar la malla por zonas para rigging si es necesario.

4. Torso y extremidades
- Flujo muscular: seguir líneas de músculo (pectorales, deltoides) para guiar edge flow.
- Caderas y pelvis: importante para transferencia de peso; mantener loops alrededor de cadera para deformación natural.
- Piernas: bucles de rodilla y pantorrilla para doblado; prestar atención a tejido blando alrededor.

5. Sculpting y detalle
- Multiresolution/ Dyntopo: en sculpt usar Multires para subdivisiones controladas; Dyntopo para formas iniciales.
- Flujo: bloquear → forms → anatomía → detalles secundarios (venas, arrugas) → microdetalles (poros, piel).
- Pinceles recomendados: Clay/ Clay Strips para masas, Crease para pliegues, Smooth para transiciones, Inflate/Grab para ajustar volumen.
- Alphas y texturas: usar mapas de alphas para poros y arrugas, tanto en sculpt como en displacement para render.

6. Retopología
- Por qué: crear malla limpia, animable y eficiente.
- Edge flow: priorizar loops alrededor de ojos, boca, articulaciones y músculos que se deforman.
- Herramientas: Retopo manual con snap, usar addons (RetopoFlow) o herramientas automáticas (Quad Remesher) y luego limpiar.
- Polígonos: mantener quads y evitar poles en áreas deformables; aceptar poles en áreas planas.

7. UVs y texturizado
- UVs: crear cortes en lugares poco visibles, mantener islas limpias y usar costuras inteligentes.
- Bake: normal maps, ambient occlusion, curvature; usar estas texturas para detalles en shaders o microdisplacement.
- PBR: usar maps (albedo, roughness, metallic, normal, displacement). Piel humana usa subsurface scattering (SSS).

8. Detalles finos y acabado
- Piel: combinar SSS con micro-normal y roughness variation para realismo.
- Poros y arrugas: baked normal/displacement a partir de sculpt de alta resolución.
- Ropa y cabello: modelar prendas base y luego simular o usar hair system para fibra. Para pelo realista, usar particle hair o cards según necesidad.

9. Animación y rigging
- Topología para deformación: loops que rodeen articulaciones (hombros, codos, rodillas) y soporte para contracción/estiramiento.
- Blendshapes / shape keys: preparar morph targets para expresiones y correcciones.

10. Buenas prácticas y performance
- LODs: generar niveles de detalle para distintos usos (renders, realtime).
- Bakes eficientes: reducir tamaño de mapas si es para realtime; aumentar resolución para close-ups.
- Documentar pipeline: mantener notas sobre resoluciones de textura, nombres de materiales y convenciones de archivo.

11. Ejemplo de flujo rápido para una cara detallada
1. Bloqueo base en cubo/esfera → 2. Definir planos faciales y proporciones → 3. Sculpt multires para anatomía → 4. Retopo a malla limpia (quads) → 5. UVs y unwrap → 6. Bake mapas desde sculpt alto → 7. Texturizar (albedo, roughness, normal) → 8. Microdetalles y SSS en shader → 9. Preparar shape keys para expresiones.

12. Recursos y aprendizaje
- Anatomía: libros de anatomía artística (p. ej. "Anatomy for Sculptors"), fotos de referencia.
- Blender: docs oficiales (docs.blender.org), tutoriales de sculpting y retopology.
- Comunidades: Blender Artists, Stack Exchange, Blender Discords.

Consejos finales
- Practicar con ejercicios: modelar una mano, una cara, y una oreja por separado.
- Usar referencias constantemente y aprender a traducir la anatomía a formas simples.
- Iterar: no buscar el detalle demasiado pronto; la buena forma general es clave para el realismo.


Fecha: 2026-08-18
