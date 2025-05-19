<?php

declare(strict_types=1);

namespace App\Logger;

enum NamespaceEnum: string
{
    case REST_KERNEL = 'kernel_restApi';
    case REST_BOOK = 'book_restApi';
    case REST_AUTHOR = 'author_restApi';
    case REST_BOOK_AUTHOR = 'book_author_restApi';
    case REST_REVIEW = 'review_restApi';
}
