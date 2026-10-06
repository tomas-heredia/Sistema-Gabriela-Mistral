<?php

return [

    /*
     * Apaga por completo la restricción de red -- sin Tailscale instalado
     * en el servidor (desarrollo local, CI, o la VPS antes de configurarlo)
     * todo pedido llegaría con una IP fuera del rango permitido, bloqueando
     * a cobrador y administra_alumnos por completo. Se activa recién en el
     * entorno donde el VPS ya está en el tailnet.
     */
    'restriccion_de_red_habilitada' => env('RESTRINGIR_ACCESO_RED', false),

    /*
     * Rango de IP de la VPN de Tailscale (CGNAT privado, RFC 6598) -- los
     * roles de RestringirAccesoPorRed::ROLES_RESTRINGIDOS solo pueden
     * acceder al sistema cuando el pedido llega desde una IP dentro de este
     * rango, es decir, a través del túnel de Tailscale.
     */
    'cidr_red_escuela' => env('CIDR_RED_ESCUELA', '100.64.0.0/10'),

];
