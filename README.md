# REDi API – Versión Laravel 8 / PHP 7.4.33

API desarrollada en **Laravel 8** y **PHP 7.4.33** para conectar el sistema **REDi** con la base de datos y servicios internos de **RED Comunicación Móvil**.

Esta versión se diseñó para entornos **PHP 7.4**

---

## Descripción

La API permite:
- Consultar información de chips mediante ICCID y DN  
- Validar vigencia y estado de recarga  
- Actualizar datos de recarga y seguimiento

El proyecto **no crea tablas nuevas**, solo **consume y actualiza registros existentes** en la base de datos actual de RED (chip_ia).

---

## Requisitos del servidor

- **PHP 7.4.33** con extensiones habilitadas:
  - openssl  
  - curl  
  - mbstring  
  - pdo_mysql  
  - mysqli  
  - fileinfo  
  - zip *(opcional)*
- **Composer 2.x**
- **MySQL 5.7+**
- **Servidor web o CLI compatible (Apache, Nginx o Artisan)**

---

## Instalación
1. Clonar el repositorio  
   `git clone https://github.com/EdgarIsmael435/apisRedCom.git`
2. Instalar dependencias  
   `composer install`
3. Copiar el archivo `.env.example` a `.env` y configurar credenciales
4. Generar la clave de aplicación  
   `php artisan key:generate`
5. Ejecutar el servidor local  
   `php artisan serve`


## Notas
- No ejecutar migraciones; la API usa la base existente de RED.  .  
- Revisa `routes/api.php` para ver los endpoints disponibles.

## Ejemplo de consumo de API (GET)
http://127.0.0.1:8000/api/getDataChip?ICCID=895205021241359150F&DN=5613437918
