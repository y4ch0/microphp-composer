<?php
echo '<h1>' . htmlspecialchars((string) $request->route('questionId'), ENT_QUOTES, 'UTF-8') . '</h1>';
