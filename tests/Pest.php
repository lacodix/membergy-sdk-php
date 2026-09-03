<?php

declare(strict_types=1);

// Shared Pest configuration. Individual tests opt in to the
// Integration suite by living under tests/Integration.

uses()->in('Unit', 'Integration');
