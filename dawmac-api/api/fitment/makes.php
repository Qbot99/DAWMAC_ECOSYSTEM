<?php
/**
 * Marki aut w bazie dopasowań.
 *
 * GET /api/fitment/makes.php
 */

require __DIR__ . '/db.php';

$res = $conn->query(
    "SELECT mk.id, mk.name, mk.slug, mk.country, COUNT(md.id) AS models
     FROM fit_make mk
     LEFT JOIN fit_model md ON md.make_id = mk.id
     GROUP BY mk.id, mk.name, mk.slug, mk.country
     ORDER BY mk.name"
);

$makes = [];
while ($r = $res->fetch_assoc()) {
    $r['id']     = (int) $r['id'];
    $r['models'] = (int) $r['models'];
    $makes[]     = $r;
}

dawmac_fit_respond(['count' => count($makes), 'makes' => $makes]);
