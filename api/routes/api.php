<?php

use Illuminate\Support\Facades\Route;

// Routes here are auto-prefixed with /api and given the api middleware group,
// so never add a manual /api prefix. Resource routes land per section as the
// endpoints are built: Categories -> Expenses -> Recurring -> Stats.
// See docs/backend-plan.md.