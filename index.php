<!DOCTYPE html>
<html>
	<head>
		<title>PHP Bug</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<link href="index.css" rel="stylesheet">
	</head>
	<body>
    <div class="root">
        This example demonstrates PHP's lack of proper handling of socket
        errors when data is being returned from MySQL. In the two examples
        below, we run a query that should return exactly 10,000 rows. But
        in the middle of returning the results, we use a proxy to forcibly
        sever the connection between PHP and MySQL. In both cases, we should
        expect an exception to indicate the at the result set was not complete
        due to a socket error. As you probably suspect, no such exception occurs
        and PHP returns the wrong number of rows.
<?php

require_once './context.php';

const OK = "\xf0\x9f\x91\x8d";
const NO = "\xf0\x9f\x91\x8e";

function runQuery(PDO $pdo): array
{
    $warnings = [];
    set_error_handler(
        function (int $errno, string $errstr) use (&$warnings) {
            if ($errno === E_WARNING) {
                $warnings[] = $errstr;
            }
            return false;
        });

    $count = 0;
    try {
        $stmt = $pdo->prepare('SELECT * FROM data');
        $stmt->execute();
        foreach ($stmt as $row) {
            $count++;
        }
        $stmt->closeCursor();
        return [$count, $warnings, null];
    } catch (Exception $e) {
        return [$count, $warnings, $exception];
    }
}

function render(
    string $desc,
    int $count,
    ?Exception $exception,
    array $warnings
) {
    $warnings = array_map(
        function ($value) {
            return "\"{$value}\"";
        },
        $warnings
    );
    printf("<div class=\"test\">\n");
    printf("<div class=\"desc\">%s</div>\n", htmlspecialchars($desc));
    printf("<div>Number of rows returned <strong>%s</strong></div>\n", $count);
    printf(
        "<div>Exception: <strong>%s</strong></div>\n",
        $exception ? $exception->getMessage() : 'None'
    );
    printf(
        "<div>Warning: <strong>%s</strong></div>\n",
        empty($warnings) ? 'None' : implode(', ', $warnings)
    );
    printf("</div>\n");
}

$ctx = new Context(
    'mysql',
    'root',
    $_ENV['MYSQL_ROOT_PASSWORD'],
    'proxy',
    10000
);

$ctx->runTest(
    true,
    102400,
    function (PDO $pdo) {
        list($count, $warnings, $exception) = runQuery($pdo);
        render(
            'First, we test with the PDO in the default buffered mode.',
            $count,
            $exception,
            $warnings
        );
    }
);

$ctx->runTest(
    false,
    102400,
    function (PDO $pdo) {
        list($count, $warnings, $exception) = runQuery($pdo);
        render(
            'Second, run the same test using unbuffered mode.',
            $count,
            $exception,
            $warnings
        );
    }
);

?>
        </div>
	</body>
</html>
