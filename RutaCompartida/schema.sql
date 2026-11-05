-- schema.sql — RutaCompartida
-- Equivalente SQL de los modelos de app/models.py.
-- Puedes ejecutarlo en phpMyAdmin (pestaña SQL) después de crear
-- la base de datos 'rutacompartida'.

SET NAMES utf8mb4;

CREATE TABLE usuarios (
    id              INT             NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    telefono        VARCHAR(20),
    avatar          VARCHAR(255),
    coche_modelo    VARCHAR(100),
    consumo_l100km  DECIMAL(4,2)    DEFAULT 6.5,
    creado_en       DATETIME        DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE viajes (
    id              INT             NOT NULL AUTO_INCREMENT,
    conductor_id    INT             NOT NULL,
    origen_txt      VARCHAR(200)    NOT NULL,
    origen_lat      DECIMAL(10,7),
    origen_lng      DECIMAL(10,7),
    destino_txt     VARCHAR(200)    NOT NULL,
    destino_lat     DECIMAL(10,7),
    destino_lng     DECIMAL(10,7),
    fecha_hora      DATETIME        NOT NULL,
    plazas_totales  INT             NOT NULL,
    plazas_libres   INT             NOT NULL,
    distancia_km    DECIMAL(8,2),
    duracion_min    INT,
    polyline        TEXT,
    precio_plaza    DECIMAL(6,2)    NOT NULL,
    comentarios     TEXT,
    estado          VARCHAR(20)     DEFAULT 'activo',
    creado_en       DATETIME        DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (conductor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE reservas (
    id              INT             NOT NULL AUTO_INCREMENT,
    viaje_id        INT             NOT NULL,
    pasajero_id     INT             NOT NULL,
    plazas          INT             NOT NULL DEFAULT 1,
    estado          VARCHAR(20)     DEFAULT 'confirmada',
    creado_en       DATETIME        DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reserva (viaje_id, pasajero_id),
    FOREIGN KEY (viaje_id)    REFERENCES viajes(id),
    FOREIGN KEY (pasajero_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE conversaciones (
    id              INT             NOT NULL AUTO_INCREMENT,
    viaje_id        INT             NOT NULL,
    conductor_id    INT             NOT NULL,
    pasajero_id     INT             NOT NULL,
    creado_en       DATETIME        DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_conversacion (viaje_id, pasajero_id),
    FOREIGN KEY (viaje_id)     REFERENCES viajes(id),
    FOREIGN KEY (conductor_id) REFERENCES usuarios(id),
    FOREIGN KEY (pasajero_id)  REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE mensajes (
    id               INT             NOT NULL AUTO_INCREMENT,
    conversacion_id  INT             NOT NULL,
    autor_id         INT             NOT NULL,
    texto            VARCHAR(1000)   NOT NULL,
    enviado_en       DATETIME        DEFAULT CURRENT_TIMESTAMP,
    leido            TINYINT(1)      DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id),
    FOREIGN KEY (autor_id)        REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
