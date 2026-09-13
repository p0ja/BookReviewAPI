<?php

declare(strict_types=1);

namespace App\Config;

class ConfigData
{
    public const BOOK_SORTING_COLUMNS = ['title', 'isbn', 'price', 'genre', 'publish_date'];
    public const REVIEW_SORTING_COLUMNS = ['name', 'rating', 'submit_date'];

    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;
}
