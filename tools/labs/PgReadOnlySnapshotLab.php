<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

use PDO;
use Throwable;

/** Shared guard for composition inside an already owned, read-only PG snapshot. */
final class PgReadOnlySnapshotLab
{
    public static function active(PDO $db):bool
    {
        try{
            if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
                ||!$db->inTransaction())return false;
            $state=$db->query(<<<'SQL'
SELECT current_setting('transaction_isolation'),
       current_setting('transaction_read_only')
SQL)->fetch(PDO::FETCH_NUM);
            return $state===['repeatable read','on'];
        }catch(Throwable){
            return false;
        }
    }
}
