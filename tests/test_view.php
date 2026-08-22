<?php
/**
 * Tests for the view helpers and the template renderer.
 *
 * e() is the application's only XSS defense, so it is tested directly rather
 * than trusted because "the views call it".
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
describe('e()');

assert_same('plain text', e('plain text'), 'ordinary text passes through unchanged');
assert_same('', e(null), 'null becomes an empty string rather than a warning');
assert_same('', e(''), 'an empty string stays empty');
assert_same(
    '&lt;script&gt;alert(1)&lt;/script&gt;',
    e('<script>alert(1)</script>'),
    'angle brackets are escaped so injected markup cannot execute'
);
assert_same('Tom &amp; Jerry', e('Tom & Jerry'), 'ampersands are escaped');
assert_same('&quot;quoted&quot;', e('"quoted"'), 'double quotes are escaped so an attribute cannot be broken out of');
assert_same('&#039;quoted&#039;', e("'quoted'"), 'single quotes are escaped too');
assert_same('café', e('café'), 'UTF-8 accented characters survive escaping intact, not mangled into entities');

// ---------------------------------------------------------------------------
describe('url()');

assert_same('index.php', url(), 'no action gives the bare front controller');
assert_same('index.php', url(''), 'an empty action gives the bare front controller');
assert_same('index.php', url('catalog'), 'the default action is left implicit in the URL');
assert_same('index.php?action=cart', url('cart'), 'a named action becomes a query string');
assert_same('index.php?action=cart.add', url('cart.add'), 'a dotted action is not mangled');
assert_same('index.php?action=cart.checkout', url('cart.checkout'), 'the checkout action builds correctly');

// ---------------------------------------------------------------------------
describe('View::render');

// Rendering an unknown template must be a loud failure, not a blank page.
assert_throws(
    static fn () => View::render('no/such/template'),
    'rendering a missing template throws'
);

// A template name is never user input, but the check costs nothing and means
// a future caller cannot turn one into a file read.
assert_throws(
    static fn () => View::render('../config/database'),
    'a path-traversing template name is rejected'
);
assert_throws(
    static fn () => View::render('catalog/../../config/database'),
    'a traversal buried mid-path is rejected'
);
