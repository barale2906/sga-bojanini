# Propuesta: inventario unificado con control por centro de costo y préstamos

**Sistema:** SGA Bojanini  
**Fecha:** 30 de octubre de 2026  
**Estado:** Propuesta para análisis del personal de la clínica. **No se ha hecho ningún cambio en el sistema.**

---

## 1. Resumen

Hoy cada centro de costo tiene su propio almacén en el sistema. Esto obliga a hacer traslados entre almacenes cada vez que un área le presta producto a otra, no deja registrado quién le debe a quién y reparte un mismo producto en muchos lugares, lo que favorece que se venza.

La propuesta separa dos preguntas que hoy el sistema mezcla:

| Pregunta | Hoy | Propuesta |
|---|---|---|
| **¿Dónde está el producto?** | En el "almacén" del centro de costo | En el **Almacén Principal** (organizado por condiciones de almacenamiento) o en **Consultorios** |
| **¿De quién es el producto?** | Se deduce del almacén donde está | Se lleva como un **saldo por centro de costo**, independiente de dónde esté guardado |

Con esto:

- El producto se guarda donde técnicamente debe estar (temperatura, humedad, espacio, capacidad), no según a quién pertenece.
- Los préstamos entre centros de costo **no requieren mover producto**: quedan registrados automáticamente.
- Siempre se puede saber **quién le prestó qué a quién**, y si ya se repuso.
- Las órdenes de compra incluyen lo que hay que reponer por préstamos.
- El producto más próximo a vencer se usa primero, sin importar qué área lo consuma.

---

## 2. Situación actual (datos reales del sistema)

Análisis de la información registrada entre el 13 y el 30 de octubre de 2026:

**Estructura**

- Hay **26 almacenes** registrados. La mayoría corresponden a centros de costo o marcas (Facial, Enfermería, Botox, Alastin, ZO, Liftera, etc.) y solo unos pocos son lugares físicos distintos (Consultorios).
- Físicamente, todo el producto está en el almacén principal, separado por anaqueles por centro de costo.
- El equipo ya empezó a unificar: el 29 de octubre se trasladó todo lo de Cons-1, Cons-1P, CONS 3, CONS 4 y CONS 7 al almacén **Consultorios** ("UNIFICACIÓN BODEGA").

**Producto repartido**

- De 353 lotes con existencias, **36 lotes están partidos entre varios almacenes**. Ejemplo: un mismo lote de 4.400 unidades está dividido entre Antienvejecimiento, Depilación Láser, Enfermería y Facial.
- **40 de 262 productos** con existencias están en más de un almacén a la vez.

**Préstamos sin control**

Hoy los préstamos se registran de dos formas, y ninguna deja la deuda anotada:

1. Como **traslado** con motivo "Préstamo" (ej.: Facial → Depilación Láser, Facial → Cooltech, Enfermería → Cooltech).
2. Como **salida desde el almacén de un centro de costo cargada a otro** (ej.: 6 salidas desde el almacén de Antienvejecimiento cargadas a Metabolismo).

En ninguno de los dos casos queda registrado que existe una deuda ni si se repuso.

**Vencimientos**

- Hay **11 registros de producto vencido** todavía en existencias, en Facial, Enfermería, Cons-1, Botox y Antienvejecimiento.
- Un mismo lote vencido aparece partido entre Facial y Antienvejecimiento.

**Compras**

- Se han registrado **445 entradas** de inventario y solo **1 orden de compra**. Ninguna entrada indica a qué centro de costo pertenece el producto: hoy esa asignación depende únicamente del almacén elegido.

---

## 3. Cómo funcionaría

### 3.1 Dónde se guarda el producto

- **Almacén Principal:** organizado por **condiciones técnicas de almacenamiento** y tipo de producto:
  - Zonas: ambiente, refrigerado, congelado y controlado, cada una con sus rangos de temperatura y humedad.
  - Anaqueles y neveras con su capacidad (volumen y peso).
  - Los productos que requieren cadena de frío van siempre a zonas refrigeradas.
- **Consultorios:** almacén desde el cual las enfermeras gestionan el consumo. Se abastece con traslados desde el Almacén Principal.

Flujo físico:

1. **Compra / Entrada** → el producto ingresa al **Almacén Principal**.
2. **Traslado (abastecimiento)** → del Almacén Principal a **Consultorios**.
3. **Salida (consumo)** → desde Consultorios, cargada al centro de costo (o paciente) que consume.
4. También se pueden hacer **salidas directas** desde el Almacén Principal.

Los traslados entre Almacén Principal y Consultorios **no cambian de quién es el producto**; solo cambian dónde está.

### 3.2 De quién es el producto: saldos por centro de costo

Cada centro de costo tiene un **saldo por producto**. Además existe un **Inventario General**: producto que se compró sin pensar en un centro de costo específico y que cualquier área puede usar.

Ejemplo con alcohol:

| | Saldo |
|---|---|
| Botox | 2 botellas |
| Antienvejecimiento | 1 botella |
| Inventario General | 5 botellas |
| **Total físico en almacenes** | **8 botellas** |

La suma de todos los saldos (centros de costo + Inventario General) **siempre debe coincidir** con las existencias físicas. El sistema lo verifica todos los días.

### 3.3 Entradas: asignación del producto

Al registrar una entrada de inventario se indica a quién pertenece lo que llega:

- Por defecto todo va al **Inventario General**.
- Opcionalmente se asigna a uno o varios centros de costo, por documento completo o por línea. Ejemplo: de 3 botellas, 2 para Botox y 1 para Antienvejecimiento.
- Si la entrada viene de una orden de compra que incluía reposición de préstamos, esas unidades se asignan automáticamente al centro de costo que prestó (ver 3.5).

La ubicación física (zona, anaquel) se elige según las condiciones del producto, no según el centro de costo.

### 3.4 Salidas y préstamos

Cuando se registra una salida, se indica el centro de costo que consume (como hoy). El sistema descuenta el saldo así:

1. Primero del **saldo propio** del centro de costo.
2. Si no alcanza, del **Inventario General** (no genera préstamo).
3. Si aún no alcanza, de **otro centro de costo**: el **almacenista elige** de cuál tomarlo (el sistema sugiere el que más saldo tiene). Esto crea automáticamente un **préstamo**.

> El orden entre los pasos 1 y 2 es una decisión pendiente (ver sección 6).

Físicamente, el producto se toma siempre del lote más próximo a vencer (FEFO), sin importar a qué centro de costo pertenezca.

**Ejemplo:** Antienvejecimiento necesita 2 botellas de alcohol, tiene saldo de 1 y el Inventario General está en 0. El almacenista registra la salida y elige que la botella faltante la preste Botox. Resultado:

- Antienvejecimiento: consume 2 (1 propia + 1 prestada).
- Botox: su saldo baja de 2 a 1.
- Queda un **préstamo abierto**: Botox → Antienvejecimiento, 1 botella de alcohol.

No hay traslado físico ni cambio de anaquel.

### 3.5 Reposición de préstamos y órdenes de compra

Cada préstamo queda registrado con: quién presta, quién recibe, producto, cantidad, la salida que lo originó, el usuario y la fecha.

Un préstamo puede estar:

| Estado | Significado |
|---|---|
| **Abierto** | Pendiente de reposición |
| **Repuesto parcialmente** | Se ha devuelto una parte |
| **Repuesto** | Devuelto completamente |
| **Cerrado sin reposición** | Se evaluó contra el consumo y se decidió no reponer. Requiere motivo y queda registrado quién lo cerró |

**Los préstamos nunca se borran**: siempre se puede consultar el historial.

**Órdenes de compra con préstamos:**

- La **sugerencia de compra** de cada producto suma lo necesario por consumo **más los préstamos abiertos** pendientes de reponer.
- Al armar la orden de compra se ven los préstamos abiertos del producto y se puede indicar que cierta cantidad es **reposición del préstamo X**.
- Cuando esa compra llega y se registra la entrada, esas unidades se asignan automáticamente al centro de costo que prestó y el préstamo se marca como repuesto (total o parcialmente).
- También se puede reponer sin orden de compra: en cualquier entrada el almacenista puede indicar que ciertas unidades reponen un préstamo.

### 3.6 Vencimientos, pérdidas y ajustes

Como el producto ya no está separado físicamente por centro de costo, hay que definir **a quién se le descuenta** una baja por vencimiento o pérdida.

**Ejemplo:** hay 10 frascos de un producto: 6 de Botox y 4 de Antienvejecimiento. Se vencen 2. ¿A quién se le descuentan?

Opciones a definir con el personal (ver sección 6):

- **a)** Repartir en proporción al saldo de cada uno (en el ejemplo, 1 y 1); el almacenista puede ajustar.
- **b)** Primero al Inventario General y, si no alcanza, proporcional entre centros de costo.
- **c)** El almacenista elige siempre a quién se descuenta.

### 3.7 Reportes nuevos

- **Préstamos abiertos:** matriz de quién le debe a quién, con cantidades y antigüedad.
- **Historial de préstamos:** abiertos, repuestos y cerrados sin reposición, con su motivo.
- **Saldo por centro de costo:** qué tiene cada área de cada producto.
- **Consumo por centro de costo:** ya existe; se mantiene.
- **Vencimientos y pérdidas por centro de costo.**
- **Sugerencia de compra** incluyendo reposición de préstamos.

---

## 4. Beneficios esperados

| Problema actual | Cómo se resuelve |
|---|---|
| Muchos traslados entre almacenes para prestar | El préstamo es un registro; no mueve producto |
| No se sabe quién le prestó a quién | Cada préstamo queda registrado con su estado e historial |
| Producto repartido en muchos lugares, propenso a vencerse | Un producto está junto en el Almacén Principal y se usa primero el lote más próximo a vencer |
| Almacenamiento según el área y no según el producto | El Almacén Principal se organiza por condiciones técnicas y capacidad |
| Las compras no consideran lo prestado | La sugerencia y la orden de compra incluyen los préstamos abiertos |

---

## 5. Transición desde la situación actual

1. **Corrección de datos** antes de migrar:
   - Fechas de vencimiento mal digitadas (ej.: `0028-02-28` en un lote de Botox, `2000-05-30` en un lote de Enfermería), que hoy aparecen como vencidas.
   - Cantidades atípicas (ej.: Liftera registra 762.000 unidades en 6 lotes; probablemente un error de unidad o presentación).
2. **Definir el mapeo almacén/marca → centro de costo** (ver sección 6). Según las salidas registradas, el sistema sugiere:

   | Almacén actual | Centro de costo sugerido |
   |---|---|
   | Botox, Consultorios (y antiguos Cons-x) | Antienvejecimiento |
   | Alastin, ZO | Productos Dermatológicos |
   | Facial, Enfermería, Cooltech, Depilación Láser, Liftera, Ultherapy, Metabolismo, Corporal, Implante Capilar, Clínica | El centro de costo del mismo nombre |
   | Colorescience, Exosomas, LaserMed, Dermazoom, Enzimas | **Por definir** |

3. **Saldos iniciales:** las existencias actuales de cada almacén se asignan como saldo inicial al centro de costo correspondiente según el mapeo. Lo que se decida que es de uso común pasa al Inventario General.
4. **Unificación física:** trasladar en el sistema el producto de los almacenes de centro de costo al Almacén Principal, a la zona y anaquel que corresponda según sus condiciones (igual que se hizo con "UNIFICACIÓN BODEGA"). Así queda la trazabilidad completa.
5. **Desactivar** los almacenes que quedan vacíos. No se borran: el historial de movimientos se conserva.
6. **Capacitación** del personal de almacén y enfermería en el registro de salidas con préstamo y en la reposición.

---

## 6. Decisiones pendientes del personal de la clínica

| # | Decisión | Opciones / Comentario |
|---|---|---|
| 1 | **Mapeo de marcas y almacenes a centros de costo** | Confirmar la tabla de la sección 5 y definir Colorescience, Exosomas, LaserMed, Dermazoom y Enzimas. ¿Hace falta crear algún centro de costo nuevo? |
| 2 | **A quién se descuentan vencimientos y pérdidas** | Opciones a), b) o c) de la sección 3.6 |
| 3 | **Orden de consumo en una salida** | ¿Primero el saldo propio y luego el Inventario General, o al revés? ¿O que lo elija el almacenista? |
| 4 | **Qué productos van al Inventario General** | ¿Hay productos que siempre deben ser de uso común (alcohol, gasas, guantes…)? ¿O se decide en cada compra? |
| 5 | **Quién puede cerrar un préstamo sin reposición** | ¿El almacenista, el coordinador del centro de costo, administración? |
| 6 | **Quién puede elegir de qué centro de costo se presta** | ¿Solo el almacenista o también las enfermeras desde Consultorios? |
| 7 | **Organización física del Almacén Principal** | Definir zonas y anaqueles por tipo de producto y condiciones, con su capacidad |

---

## 7. Próximos pasos

1. El personal de la clínica revisa este documento y responde las decisiones de la sección 6.
2. Con esas respuestas se hace el diseño técnico detallado y se estima el esfuerzo.
3. Se corrigen los datos indicados en la sección 5.
4. Desarrollo, pruebas y capacitación.
5. Migración de los datos actuales y puesta en marcha.

---

## Anexo técnico (para el equipo de desarrollo)

Resumen de los cambios previstos en el sistema, sujeto a las decisiones de la sección 6:

| Área | Cambio |
|---|---|
| Centros de costo | Libro de saldos por centro de costo y producto; registro de préstamos con estados; Inventario General; reportes |
| Entradas | Asignación por centro de costo (por documento y por línea); reposición de préstamos; sugerencia de zona según cadena de frío y capacidad |
| Salidas y kits | Validación de saldo, selección del centro de costo que presta, registro de consumo y préstamo; FEFO sin cambios |
| Bajas (vencimiento, pérdida, ajuste) | Reparto de la baja entre centros de costo según la regla elegida |
| Traslados | Sin cambios: no afectan la propiedad |
| Compras | Sugerencia de compra con préstamos abiertos; líneas de la orden marcadas como reposición |
| Conciliación diaria | Verificar que la suma de saldos por centro de costo e Inventario General coincida con las existencias físicas |
| Almacenes | Sin cambios de estructura; solo migración y desactivación de almacenes vacíos |
