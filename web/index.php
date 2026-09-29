<?php
/*
 * THE WALL. Jede Adresse, zu der es keine Datei gibt, landet hier
 * (nginx: try_files $uri $uri/ /index.php?$args). Code liegt in .htapp.
 */

declare(strict_types=1);

require __DIR__ . '/.htapp/bootstrap.php';

app_run();
