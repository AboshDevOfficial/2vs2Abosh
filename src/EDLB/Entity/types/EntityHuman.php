<?php
declare(strict_types=1);

namespace EDLB\Entity\types;
use pocketmine\{Server, Player, entity\Human};

class EntityHuman extends Human {

    public function getName() : string {
        return '';
    }
}
?>
