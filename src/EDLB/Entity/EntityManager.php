<?php
declare(strict_types=1);

namespace EDLB\Entity;
use EDLB\{Entity\types\EntityHuman};
use pocketmine\{Server, Player, entity\Skin, entity\Entity, level\Level, math\Vector3};

class EntityManager {

    public function setEntity(Player $player) {
        $nbt = Entity::createBaseNBT(new Vector3((float)$player->getX(), (float)$player->getY(), (float)$player->getZ()));
        $nbt->setTag(clone $player->namedtag->getCompoundTag('Skin'));
        $human = new types\EntityHuman($player->getLevel(), $nbt);
        $human->setNameTag('');
        $human->setNameTagVisible(true);
        $human->setNameTagAlwaysVisible(true);
        $human->yaw = $player->getYaw();
        $human->pitch = $player->getPitch();
        $human->spawnToAll();
    }
}
?>
