<?php

use Modules\Helpdesk\Services\KnowledgeBaseService;

test('boolean-mode operators are stripped from search terms', function () {
    $service = new KnowledgeBaseService();

    $method = new ReflectionMethod($service, 'sanitizeBooleanModeTerm');
    $method->setAccessible(true);

    expect($method->invoke($service, '+admin* -"drop"(table)~@'))->toBe('admin drop table');
    expect($method->invoke($service, 'normal search'))->toBe('normal search');
    expect($method->invoke($service, '***'))->toBe('');
});
