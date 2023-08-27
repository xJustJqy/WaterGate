<?php

namespace xJustJqy\wg\client;

use pocketmine\player\Player;
use pocketmine\player\XboxLivePlayerInfo;
use ReflectionClass;

class WaterDogPlayer extends Player
{

	private bool $fixedXUID = false;
	private bool $fixedIP = false;

	public function fixXUID(string $xuid)
	{
		if ($this->fixedXUID) return;
		$this->fixedXUID = true;
		if ($this->playerInfo instanceof XboxLivePlayerInfo) {
			$reflector = new ReflectionClass($this->playerInfo);
			$prop = $reflector->getProperty('xuid');
			$prop->setAccessible(true);
			$prop->setValue($this->playerInfo, $xuid);
		}
		else $this->playerInfo = new XboxLivePlayerInfo($xuid, $this->username, $this->uuid, $this->skin, $this->locale, $this->playerInfo->getExtraData());
		$this->xuid = $xuid;
	}

	public function fixIP(string $ip)
	{
		if ($this->fixedIP) return;
		$this->fixedIP = true;
		$reflector = new ReflectionClass($this->networkSession);
		$prop = $reflector->getProperty('ip');
		$prop->setAccessible(true);
		$prop->setValue($this->networkSession, $ip);
	}
}
