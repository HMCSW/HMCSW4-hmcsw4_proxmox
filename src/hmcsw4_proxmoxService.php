<?php

namespace hmcswModule\hmcsw4_proxmox\src;

use Exception;
use hmcsw\service\Services;
use hmcsw\service\module\ModuleRepository;
use hmcsw\objects\user\teams\service\Service;
use hmcsw\service\module\ModuleServiceRepository;
use hmcsw\objects\user\teams\service\ServiceRepository;

class hmcsw4_proxmoxService implements ServiceRepository
{

  protected Service $service;
  protected ModuleServiceRepository $module;

  public array $get = ["success" => false];
  public ?\HMCSW4\Client\Resources\Service $externalOBJ = null;

  public function __construct (Service $service, ModuleServiceRepository $module)
  {
    $this->service = $service;
    $this->module = $module;

    if ($this->service->hostOBJ->host_id != 0) $this->externalOBJ = $this->getExternalOBJ();
    $this->get = $this->get();
  }

  public function getService (): Service
  {
    return $this->service;
  }

  public function getModule (): ModuleServiceRepository
  {
    return $this->module;
  }

  public function onCreate (bool $reinstall = false): array
  {
    $host = $this->getService()->hostOBJ;
    $package = $this->getService()->package;
    $object = (new \HMCSW4\Client\HMCSW4($host->domain, $host->auth['password']))->getTeam($host->auth['user'])->orderCustomBuy("vps", [
        "disk" => $package['specs']['disk'],
        "memory" => $package['specs']['memory'],
        "cores" => $package['specs']['cpu'] / 100,
        "ipv4" => $package['ipv4']['count'],
        "ipv6" => $package['ipv6']['count']
      ]
    );

    $statement = Services::getDatabaseService()->prepare("UPDATE services SET external_id = ?, host_id = ? WHERE service_id = ?", [$object['service_id'], $this->getService()->host['host_id'], $this->getService()->service_id]);

    return ["success" => true];
  }

  public function onDelete (bool $reinstall = false): array
  {
    try {
      return ["success" => true, "response" => $this->externalOBJ->terminateInstant()];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function onEnable (): array
  {
    try {
      return $this->startVM();
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function onDisable (): array
  {
    try {
      return $this->stopVM();
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function onTerminate (): array
  {
    return ["success" => true];
  }

  public function onTerminateInstant (): array
  {
    return ["success" => true];
  }

  public function onWithdrawTerminate (): array
  {
    return ["success" => false];
  }

  public function onExtend (int $time): array
  {
    return ["success" => true];
  }

  public function onLogin (string $key): array
  {
    $session = $this->getExternalOBJ()->createSession();
    return ["success" => true, "response" => ["url" => $session['url'], "type" => "iframe"]];
  }

  public function onSetName (string $name): array
  {
    return ["success" => true];
  }

  public function getData (): array
  {
    return $this->get;
  }

  public function getExternalOBJ (): \HMCSW4\Client\Resources\Service
  {
    if (!is_null($this->externalOBJ)) {
      return $this->externalOBJ;
    } else{
      $host = $this->getService()->hostOBJ;
      $host_ipv4 = $host->ip['v4'];
      $host_port = $host->port;

      $object = (new \HMCSW4\Client\HMCSW4($host->domain, $host->auth['password']))->getTeam($host->auth['user'])->getService($this->getService()->external['id']);

      $this->externalOBJ = $object;
      return $object;
    }

  }
  public function getStats (bool $chartJSReady = true, $by = "hour"): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->stats($chartJSReady, $by)];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function killVM (): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->kill()];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function startVM (): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->start()];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function stopVM (): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->stop()];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function restartVM (): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->reboot()];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }
  public function renewService (): array
  {
    return ["success" => true];
  }

  public function addISO (string $name): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->addISO($name)];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function removeISO (): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->removeISO()];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function changePassword (string $password): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->changePassword($password)];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function createBackup(): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->createBackup()];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function deleteBackup(string $name): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->deleteBackup($name)];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function restoreBackup(string $name): array
  {
    try {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->restoreBackup($name)];
    } catch(Exception $e){
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage()]];
    }
  }

  public function getRDns (): array
  {
    if($this->getService()->external['id'] == 0) {
      return ["success" => false];
    } else {
      return ["success" => true, "response" => $this->getExternalOBJ()->getSpecifiedType("proxmox")->getRDNS()];
    }
  }

  public function get (): array
  {
    if($this->get['success']) return $this->get;

    if($this->getService()->external['id'] == 0) {
      return ["success" => false];
    } else {
      $request = $this->getExternalOBJ()->getSpecifiedType("proxmox")->get();

      $this->get = $request;
      return ["success" => true, "response" => $this->get];
    }
  }


}