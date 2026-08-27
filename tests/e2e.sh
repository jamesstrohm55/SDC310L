#!/bin/bash
# ---------------------------------------------------------------------------
# End-to-end tests for the SDC310L online store.
#
# SDC310L Online Store — James Strohm (jamstr441)
#
#     tests/e2e.sh [base-url]
#
# The PHP suite (tests/run.php) checks the models and the router in isolation.
# This script checks the thing a visitor actually uses: a real browser session
# against Apache, PHP, and MariaDB, driving every route over HTTP with a
# cookie jar so the session cart persists between requests exactly as it does
# in a browser.
#
# Every check prints what it expected and what it got. The script exits 0 only
# if every check passed, so a failure cannot be swallowed by a pipe.
#
# Requires the store to be served (see the README) and the onlinestore
# database to be imported.
# ---------------------------------------------------------------------------

set -u

BASE="${1:-http://localhost/SDC310L}"
ENTRY="$BASE/index.php"
MYSQL="/Applications/XAMPP/xamppfiles/bin/mariadb"

WORK="$(mktemp -d)"

# The escaping check inserts a probe product and deletes it again. If the run
# is interrupted between those two points the row survives, and the next run
# fails its product count in area A — which reads as an application regression
# rather than as dirty state. The row is therefore cleaned up on any exit.
PROBE_ID=9001
cleanup() {
    rm -rf "$WORK"
    "$MYSQL" -u root -e \
        "DELETE FROM onlinestore.products WHERE product_id = $PROBE_ID;" 2>/dev/null
}
trap cleanup EXIT INT TERM

PASSED=0
FAILED=0
CURRENT_AREA=""

# --- harness ---------------------------------------------------------------

area() {
    CURRENT_AREA="$1"
    printf '\n\033[1m%s\033[0m\n' "$1"
}

# check <label> <expected> <actual>
check() {
    local label="$1" expected="$2" actual="$3"
    if [ "$expected" = "$actual" ]; then
        PASSED=$((PASSED + 1))
        printf '  ok    %s\n' "$label"
    else
        FAILED=$((FAILED + 1))
        printf '  FAIL  %s\n        expected: %s\n        actual:   %s\n' \
            "$label" "$expected" "$actual"
    fi
}

# check_contains <label> <needle> <file>
check_contains() {
    local label="$1" needle="$2" file="$3"
    if grep -qF -- "$needle" "$file"; then
        PASSED=$((PASSED + 1))
        printf '  ok    %s\n' "$label"
    else
        FAILED=$((FAILED + 1))
        printf '  FAIL  %s\n        page does not contain: %s\n' "$label" "$needle"
    fi
}

# check_flat <label> <needle> <file>
#
# Like check_contains, but collapses the page's whitespace to single spaces
# first so a needle can span the source's line breaks. This exists because
# grep -F treats an embedded newline in the PATTERN as alternation, not as a
# literal: a two-line needle silently passes if either half is present, which
# made the disabled-button check below vacuous until a code review caught it.
check_flat() {
    local label="$1" needle="$2" file="$3"
    if tr '\n' ' ' < "$file" | tr -s ' ' | grep -qF -- "$needle"; then
        PASSED=$((PASSED + 1))
        printf '  ok    %s\n' "$label"
    else
        FAILED=$((FAILED + 1))
        printf '  FAIL  %s\n        page does not contain: %s\n' "$label" "$needle"
    fi
}

# check_absent <label> <needle> <file>
check_absent() {
    local label="$1" needle="$2" file="$3"
    if grep -qF -- "$needle" "$file"; then
        FAILED=$((FAILED + 1))
        printf '  FAIL  %s\n        page unexpectedly contains: %s\n' "$label" "$needle"
    else
        PASSED=$((PASSED + 1))
        printf '  ok    %s\n' "$label"
    fi
}

# --- request helpers -------------------------------------------------------

JAR="$WORK/session.jar"

# page <jar> <action> -> writes body to $WORK/page.html, echoes status
page() {
    curl -s -c "$1" -b "$1" -o "$WORK/page.html" -w '%{http_code}' \
        "$ENTRY${2:+?action=$2}"
}

# token <jar> -> the CSRF token embedded in the current catalog page
token() {
    curl -s -c "$1" -b "$1" "$ENTRY" \
        | grep -o 'name="csrf_token" value="[0-9a-f]*"' \
        | head -1 | sed 's/.*value="//; s/"//'
}

# post <jar> <action> <data...> -> echoes "status redirect-target"
post() {
    local jar="$1" action="$2"; shift 2
    curl -s -c "$jar" -b "$jar" -o /dev/null -w '%{http_code} %{redirect_url}' \
        -d "$*" "$ENTRY?action=$action"
}

# qty <jar> <product_id> -> the quantity shown for that product on the catalog
#
# data-qty is a test hook on the element holding the number, so the anchor is
# the number's own element rather than a nearby label. The label moved once
# already for an accessibility fix and silently took this helper with it.
qty() {
    curl -s -c "$1" -b "$1" "$ENTRY" \
        | tr '\n' ' ' \
        | grep -o "data-qty=\"$2\">[0-9]*" \
        | grep -o '[0-9]*$'
}

# summary <jar> <label> -> the dollar figure the cart's order summary shows
summary() {
    curl -s -c "$1" -b "$1" "$ENTRY?action=cart" \
        | tr '\n' ' ' \
        | grep -o "$2[^<]*</dt>[[:space:]]*<dd[^>]*>\$[0-9,.]*" \
        | grep -o '[0-9,.]*$'
}

# read_row_count -> sets $ROW_COUNT, or returns non-zero.
#
# Without this guard, a missing client or a stopped database makes both sides
# of a before/after comparison the empty string, and comparing '' to '' reports
# a pass — the same masked failure the PHP suite's exit code was fixed for, and
# it would silently gut the injection check below.
#
# The count is returned through a global rather than by echoing it, because a
# function called as $(...) runs in a subshell where `exit` only leaves the
# subshell. The first version of this guard did exactly that: the script sailed
# past a dead database and still reported 69 checks passed.
ROW_COUNT=''
read_row_count() {
    local n
    n=$("$MYSQL" -u root -N -e "SELECT COUNT(*) FROM onlinestore.products;" 2>/dev/null) \
        || return 1
    case "$n" in
        ''|*[!0-9]*) return 1 ;;
    esac
    ROW_COUNT="$n"
}

require_database() {
    if ! read_row_count; then
        printf '\nFATAL: could not read the products table using %s\n' "$MYSQL" >&2
        printf 'The database checks cannot run, so this script will not report a pass.\n' >&2
        exit 2
    fi
}

printf '\033[1mSDC310L end-to-end tests\033[0m\n'
printf 'Target: %s\n' "$BASE"

# Fail loudly and early rather than half-running.
require_database
printf 'Products in the database: %s\n' "$ROW_COUNT"

# ===========================================================================
area 'A. Catalog page'

rm -f "$JAR"
STATUS=$(page "$JAR" '')
check 'the catalog answers a GET with 200' '200' "$STATUS"
check_contains 'the store name is rendered' 'Summit Outfitters' "$WORK/page.html"
check 'all six seeded products are listed' '6' \
    "$(grep -c 'class="product-card' "$WORK/page.html")"

# Every attribute the Course Project Overview requires must be on the page.
check_contains 'a product id is shown'          'Product ID' "$WORK/page.html"
check_contains 'a product name is shown'        'Trailhead 45L Backpack' "$WORK/page.html"
check_contains 'a product description is shown' 'internal frame pack' "$WORK/page.html"
check_contains 'a product cost is shown'        '$129.99' "$WORK/page.html"
check_contains 'quantity ordered is shown'      'Quantity ordered' "$WORK/page.html"
check_contains 'an add-to-cart control exists'  'Add to Cart' "$WORK/page.html"

check 'a fresh session shows every quantity as zero' '0' "$(qty "$JAR" 1)"
check_flat 'the minus button is disabled at zero' \
    'aria-label="Decrease quantity of Trailhead 45L Backpack" disabled' \
    "$WORK/page.html"

# ===========================================================================
area 'B. Cart operations'

rm -f "$JAR"
T=$(token "$JAR")

RESULT=$(post "$JAR" 'cart.add' "product_id=1&return=catalog&csrf_token=$T")
check 'adding a product answers 303 rather than rendering' '303' "${RESULT%% *}"
check 'the redirect target is the catalog' "$ENTRY" "${RESULT##* }"
check 'the product is now in the cart at quantity 1' '1' "$(qty "$JAR" 1)"

post "$JAR" 'cart.add' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
check 'adding the same product again raises the quantity' '2' "$(qty "$JAR" 1)"

post "$JAR" 'cart.increase' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
check 'increase raises the quantity by one' '3' "$(qty "$JAR" 1)"

post "$JAR" 'cart.decrease' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
check 'decrease lowers the quantity by one' '2' "$(qty "$JAR" 1)"

post "$JAR" 'cart.remove' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
check 'remove drops the product entirely' '0' "$(qty "$JAR" 1)"

# The quantity must clamp at zero. A negative quantity would produce a
# negative line total and silently reduce the order total.
post "$JAR" 'cart.decrease' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
post "$JAR" 'cart.decrease' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
check 'decreasing an absent product never goes below zero' '0' "$(qty "$JAR" 1)"

post "$JAR" 'cart.add' "product_id=2&return=cart&csrf_token=$T" > /dev/null
RESULT=$(post "$JAR" 'cart.add' "product_id=2&return=cart&csrf_token=$T")
check 'a form submitted from the cart returns to the cart' \
    "$ENTRY?action=cart" "${RESULT##* }"
post "$JAR" 'cart.remove' "product_id=2&return=catalog&csrf_token=$T" > /dev/null

# ===========================================================================
area 'C. Cart page and order totals'

rm -f "$JAR"
T=$(token "$JAR")

STATUS=$(page "$JAR" 'cart')
check 'an empty cart answers 200' '200' "$STATUS"
check_contains 'an empty cart says so' 'Your cart is empty' "$WORK/page.html"
check_absent 'an empty cart shows no order summary' 'Order Total' "$WORK/page.html"

# A fixed basket with figures that can be checked by hand:
#   2 x Trailhead 45L Backpack   @ 129.99 = 259.98
#   1 x Cascade 2-Person Tent    @ 249.00 = 249.00
#                          items total      508.98
#   tax           5% of 508.98 = 25.449  -> 25.45
#   shipping     10% of 508.98 = 50.898  -> 50.90
#                          order total      585.33
post "$JAR" 'cart.add'      "product_id=1&return=catalog&csrf_token=$T" > /dev/null
post "$JAR" 'cart.increase' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
post "$JAR" 'cart.add'      "product_id=3&return=catalog&csrf_token=$T" > /dev/null

STATUS=$(page "$JAR" 'cart')
check 'a populated cart answers 200' '200' "$STATUS"
check_contains 'the cart lists a product name'  'Trailhead 45L Backpack' "$WORK/page.html"
check_contains 'the cart shows the unit cost'   '$129.99' "$WORK/page.html"
check_contains 'the cart shows the line total'  '$259.98' "$WORK/page.html"
check_contains 'the cart shows the second line' '$249.00' "$WORK/page.html"

check 'total of items ordered is correct' '508.98' "$(summary "$JAR" 'Total of Items Ordered')"
check 'tax is 5% of the pre-tax total'    '25.45'  "$(summary "$JAR" 'Tax (5%)')"
check 'shipping is 10% of the pre-tax total' '50.90' "$(summary "$JAR" 'Shipping')"
check 'the order total is items + tax + shipping' '585.33' "$(summary "$JAR" 'Order Total')"

# The four checks above pin the exact figures for one known basket, which is
# what catches drift between milestones. These three re-derive tax, shipping,
# and the order total independently from the item total actually shown on the
# page, applying the documented rules in whole cents. That is what catches a
# changed rate or a changed rounding rule for any basket, not just this one.
#
# Two earlier versions of this check were worth less than they looked. The
# first summed three hardcoded literals and compared them to a fourth — a
# tautology over number_format. The second summed the three figures read off
# the page and compared them to the page's total, which can never fail while
# the total is computed as exactly that sum. Both were confirmed useless by
# breaking the tax rule and watching them stay green.
ITEMS=$(summary "$JAR" 'Total of Items Ordered' | tr -d ',')
DERIVED=$(awk -v items="$ITEMS" 'BEGIN {
    cents = int(items * 100 + 0.5);
    tax   = int(cents * 0.05 + 0.5);
    ship  = int(cents * 0.10 + 0.5);
    printf "%.2f %.2f %.2f", tax / 100, ship / 100, (cents + tax + ship) / 100;
}')
check 'tax matches 5% of the item total shown on the page' \
    "$(echo "$DERIVED" | cut -d' ' -f1)" "$(summary "$JAR" 'Tax (5%)' | tr -d ',')"
check 'shipping matches 10% of the item total shown on the page' \
    "$(echo "$DERIVED" | cut -d' ' -f2)" "$(summary "$JAR" 'Shipping' | tr -d ',')"
check 'the order total matches items plus tax plus shipping' \
    "$(echo "$DERIVED" | cut -d' ' -f3)" "$(summary "$JAR" 'Order Total' | tr -d ',')"

# ===========================================================================
area 'D. Checkout'

RESULT=$(post "$JAR" 'cart.checkout' "csrf_token=$T")
check 'checkout answers 303' '303' "${RESULT%% *}"
check 'checkout returns to the catalog' "$ENTRY" "${RESULT##* }"

page "$JAR" '' > /dev/null
check_contains 'the visitor is thanked for the order' 'Thank you for your order' "$WORK/page.html"
check 'the cart is empty after checkout' '0' "$(qty "$JAR" 1)"

page "$JAR" '' > /dev/null
check_absent 'the confirmation shows only once' 'Thank you for your order' "$WORK/page.html"

page "$JAR" 'cart' > /dev/null
check_contains 'the cart page reports itself empty' 'Your cart is empty' "$WORK/page.html"

# ===========================================================================
area 'E. Routing'

STATUS=$(page "$JAR" 'nope')
check 'an unknown action answers 404' '404' "$STATUS"
check_contains 'the 404 page is a real page, not a blank' 'Page Not Found' "$WORK/page.html"

# A cart mutation reached by GET must change nothing. This is what keeps
# Post/Redirect/Get intact against a stray link or a bookmarked form target.
rm -f "$JAR"
T=$(token "$JAR")
post "$JAR" 'cart.add' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
STATUS=$(curl -s -c "$JAR" -b "$JAR" -o /dev/null -w '%{http_code}' \
    "$ENTRY?action=cart.add&product_id=2")
check 'a POST route reached by GET redirects' '303' "$STATUS"
check 'a POST route reached by GET changes nothing' '0' "$(qty "$JAR" 2)"

# HEAD is a GET with no body. If it did not resolve, the front controller
# would read it as a wrong-verb hit and redirect it to itself forever.
STATUS=$(curl -s -I -o /dev/null -w '%{http_code}' "$ENTRY")
check 'HEAD on the catalog answers 200, not a redirect loop' '200' "$STATUS"

# ===========================================================================
area 'F. Security'

# --- CSRF ---
rm -f "$JAR"
T=$(token "$JAR")
post "$JAR" 'cart.add' "product_id=1&return=catalog&csrf_token=$T" > /dev/null
check 'a POST carrying the right token is accepted' '1' "$(qty "$JAR" 1)"

post "$JAR" 'cart.add' "product_id=1&return=catalog" > /dev/null
check 'a POST carrying no token changes nothing' '1' "$(qty "$JAR" 1)"

post "$JAR" 'cart.add' "product_id=1&return=catalog&csrf_token=" > /dev/null
check 'a POST carrying an empty token changes nothing' '1' "$(qty "$JAR" 1)"

post "$JAR" 'cart.add' \
    "product_id=1&return=catalog&csrf_token=00000000000000000000000000000000" > /dev/null
check 'a POST carrying a wrong token changes nothing' '1' "$(qty "$JAR" 1)"

# A token is only valid in the session it was minted for, or one visitor's
# token would authorise a request forged against another.
OTHER="$WORK/other.jar"
rm -f "$OTHER"
OTHER_T=$(token "$OTHER")
post "$JAR" 'cart.add' "product_id=1&return=catalog&csrf_token=$OTHER_T" > /dev/null
check 'a token from another session changes nothing' '1' "$(qty "$JAR" 1)"

# The rejection must explain itself. The page is fetched immediately after
# the rejected POST and nothing is asserted in between: a flash message is
# consumed by the first page that renders it, and qty() renders one — so an
# intervening quantity check would take the message before this could see it.
post "$JAR" 'cart.add' "product_id=1&return=catalog" > /dev/null
page "$JAR" '' > /dev/null
check_contains 'a rejected request explains itself' 'could not be verified' "$WORK/page.html"
check_contains 'the rejection renders as a warning, not a success' \
    'notice notice-warning' "$WORK/page.html"

# --- input validation ---
post "$JAR" 'cart.add' "product_id=9999&return=catalog&csrf_token=$T" > /dev/null
page "$JAR" 'cart' > /dev/null
check_absent 'a product id that does not exist is not added' '9999' "$WORK/page.html"

# (int) on an array is 1 in PHP with no warning, so an array-valued id would
# otherwise act on product 1.
rm -f "$JAR"
T=$(token "$JAR")
post "$JAR" 'cart.add' "product_id[]=9999&return=catalog&csrf_token=$T" > /dev/null
check 'an array-valued product id does not act on product 1' '0' "$(qty "$JAR" 1)"

# --- SQL injection ---
require_database
BEFORE="$ROW_COUNT"
post "$JAR" 'cart.add' \
    "product_id=1%29%3B+DROP+TABLE+products%3B+--&return=catalog&csrf_token=$T" > /dev/null
post "$JAR" 'cart.add' \
    "product_id=%27%29%3B+DROP+TABLE+products%3B+--&return=catalog&csrf_token=$T" > /dev/null
page "$JAR" 'cart' > /dev/null
require_database
AFTER="$ROW_COUNT"
check 'the products table survives an injection payload' "$BEFORE" "$AFTER"

# --- output escaping ---
# A product name containing markup must be escaped on the way out. Inserted
# here and removed again, so the check runs against the real render path
# rather than against e() in isolation.
$MYSQL -u root -e "DELETE FROM onlinestore.products WHERE product_id = $PROBE_ID;"
$MYSQL -u root -e "INSERT INTO onlinestore.products
    (product_id, product_name, product_description, product_cost)
    VALUES ($PROBE_ID, '<script>alert(1)</script>', 'XSS probe', 1.00);"
page "$JAR" '' > /dev/null
check_absent 'an injected script tag is not emitted raw' '<script>alert(1)</script>' "$WORK/page.html"
check_contains 'an injected script tag is escaped' '&lt;script&gt;alert(1)&lt;/script&gt;' "$WORK/page.html"
$MYSQL -u root -e "DELETE FROM onlinestore.products WHERE product_id = $PROBE_ID;"
require_database
check 'the probe product is removed again' "$BEFORE" "$ROW_COUNT"

# --- open redirect ---
rm -f "$JAR"
T=$(token "$JAR")
RESULT=$(post "$JAR" 'cart.add' \
    "product_id=1&return=https://example.com/&csrf_token=$T")
check 'an off-site return value falls back to the catalog' "$ENTRY" "${RESULT##* }"

# --- direct file access ---
for path in config/database.php core/Router.php models/Cart.php \
            controllers/CartController.php views/layout.php tests/run.php; do
    STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/$path")
    check "$path is not reachable directly" '403' "$STATUS"
done

STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/css/style.css")
check 'the stylesheet is still reachable' '200' "$STATUS"

# ===========================================================================
area 'G. Session isolation'

A="$WORK/a.jar"; rm -f "$A"
B="$WORK/b.jar"; rm -f "$B"
TA=$(token "$A")
TB=$(token "$B")
post "$A" 'cart.add' "product_id=1&return=catalog&csrf_token=$TA" > /dev/null
post "$A" 'cart.add' "product_id=1&return=catalog&csrf_token=$TA" > /dev/null
post "$B" 'cart.add' "product_id=1&return=catalog&csrf_token=$TB" > /dev/null
check "one visitor's cart holds their own quantity" '2' "$(qty "$A" 1)"
check "another visitor's cart is unaffected"        '1' "$(qty "$B" 1)"
check 'the two sessions were issued different tokens' 'different' \
    "$([ "$TA" != "$TB" ] && echo different || echo same)"

# ===========================================================================
printf '\n%s\n' "------------------------------------------------------------"
printf '%d passed, %d failed\n' "$PASSED" "$FAILED"

[ "$FAILED" -eq 0 ] || exit 1
exit 0
