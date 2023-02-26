<?php

namespace hmcswModule\hmcsw4_proxmox\src;

use hmcsw\controller\web\error\error;
use hmcsw\objects\user\teams\service\Service;
use hmcsw\objects\user\teams\service\ServiceRepository;
use hmcsw\service\authorization\SessionService;
use hmcsw\service\config\ConfigService;
use hmcsw\service\module\ModuleServiceRepository;
use hmcsw\service\templates\AssetsService;
use hmcsw\service\templates\LanguageService;
use hmcsw\service\templates\TwigService;

class hmcsw4_proxmox implements ModuleServiceRepository
{
  public array $config;

  public function __construct ()
  {
    $this->config = json_decode(file_get_contents(__DIR__ . '/../config/config.json'), true);
  }

  public function startModule (): bool
  {
    if ($this->config['enabled']) {

      return true;
    } else {
      return false;
    }
  }

  public function getMessages (string $lang): array|bool
  {
    if (!file_exists(__DIR__ . '/../messages/' . $lang . '.json')) {
      return false;
    }

    return json_decode(file_get_contents(__DIR__ . '/../messages/' . $lang . '.json'), true);
  }

  public function getModuleInfo (): array
  {
    return json_decode(file_get_contents(__DIR__ . '/../module.json'), true);
  }

  public function getProperties (): array
  {
    return json_decode(file_get_contents(__DIR__ . '/../properties.json'), true);
  }

  public function getConfig (): array
  {
    return $this->config;
  }

  public function getInstance (Service $service): ServiceRepository
  {
    return new hmcsw4_proxmoxService($service, $this);
  }

  public function loadPage (array $args, ServiceRepository $serviceRepository): void
  {
    if (isset($_GET['vnc'])) {
      $action = $serviceRepository->getService()->createLoginInSession();
      if ($action['success']) {
        header('Location: ' . $action['response']['url']);
      } else {
        (new error())->serverError();
      }
      die();
    }


    $rDns = $serviceRepository->getRDNS();

    $get = $serviceRepository->getData();

    AssetsService::addJS("
      <script>
        function popupCenter(url, title, w, h) {
          const dualScreenLeft = window.screenLeft !== undefined ? window.screenLeft : screen.left;
          const dualScreenTop = window.screenTop !== undefined ? window.screenTop : screen.top;
          var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
          var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;
  
          const left = ((width / 2) - (w / 2)) + dualScreenLeft;
          const top = ((height / 2) - (h / 2)) + dualScreenTop;
          const newWindow = window.open(url, title, 'scrollbars=yes, width=' + w + ', height=' + h + ', top=' + top + ', left=' + left);
  
          if (window.focus) {
            newWindow.focus();
          }
        }
      </script>
      ");
    AssetsService::addJS('
       <script>
                    $(document).ready(function() {
                        // LOADING BAR STATUS
                        const statusLoading = $("#statusLoading");

                        const statusDiv = $("#statusDiv");

                        // FORM
                        const stopForm = $("#stopForm");
                        const startForm = $("#startForm");
                        const rebootForm = $("#rebootForm");
                        const killForm = $("#killForm");


                        stopForm.on("submit", function(e) {
                            statusDiv.css("display", "none");
                            statusLoading.css("display", "");
                        });
                        startForm.on("submit", function(e) {
                            statusDiv.css("display", "none");
                            statusLoading.css("display", "");
                        });
                        rebootForm.on("submit", function(e) {
                            statusDiv.css("display", "none");
                            statusLoading.css("display", "");
                        });
                        killForm.on("submit", function(e) {
                            statusDiv.css("display", "none");
                            statusLoading.css("display", "");
                        });

                    });
       </script>
      ');


    AssetsService::addJS('<script src="/assets/js/chartJS/chart.min.js"></script>');
    AssetsService::addJS('
      <script>
        $(function () {
          var cpuStatsChart = document.getElementById("cpuStats").getContext("2d");
          var memStatsChart = document.getElementById("memStats").getContext("2d");
          var netStatsChart = document.getElementById("netStats").getContext("2d");
          var json_url = "' . ConfigService::getApiUrl() . '/v1/user/teams/' . $serviceRepository->getService()->getTeam()->team_id . '/services/' . $serviceRepository->getService()->service_id . '/stats";
      
          // draw empty chart
          var cpuStats = new Chart(cpuStatsChart, {
              type: "line",
              data: {
                  labels: [],
                  datasets: [{
                    label: "' . LanguageService::getMessage("site.cp.service.tab.proxmox.stats.cpu") . '",
                    borderColor: "#40FF00",
                  }]
              },
              options: {
                  scales: {
                      yAxes: [{
                          ticks: {
                              beginAtZero:true,
                              max: 100,
                          }
                      }]
                  },
                  tooltips: {
                    callbacks: {
                      label: function(tooltipItems, data) { 
                        return tooltipItems.yLabel + " %";
                      }
                    },
                  }
              },
             
          });
          
          var memStats = new Chart(memStatsChart, {
              type: "line",
              data: {
                  labels: [],
                  datasets: [{
                    label: "' . LanguageService::getMessage("site.cp.service.tab.proxmox.stats.memory") . '",
                    borderColor: "#40FF00",
                  }]
              },
              
              options: {
                  scales: {
                      yAxes: [{
                          ticks: {
                              beginAtZero:true,
                              max: ' . $args['service']['package']['specs']['memory'] . '
                          }
                      }]
                  },
                  tooltips: {
                    callbacks: {
                      label: function(tooltipItems, data) { 
                        return tooltipItems.yLabel + " MB";
                      }
                    },
                  }
               
              }
            }
          );
          
          var netStats = new Chart(netStatsChart, {
              type: "line",
              data: {
                  labels: [],
                  datasets: [{
                    label: "' . LanguageService::getMessage("site.cp.service.tab.proxmox.stats.netIn") . '",
                    borderColor: "#08962b",
                  }, {
                    label: "' . LanguageService::getMessage("site.cp.service.tab.proxmox.stats.netOut") . '",
                    borderColor: "#4785ff",
                  }]
              },
              
              options: {
                  scales: {
                      yAxes: [{
                          ticks: {
                              beginAtZero:true,
                          }
                      }]
                  },
                  tooltips: {
                    callbacks: {
                      label: function(tooltipItems, data) { 
                        return tooltipItems.yLabel + " MB";
                      }
                    },
                  }
               
              }
            }
          );
                
          ajax_chart(json_url, cpuStats, memStats, netStats);
          setInterval(function(){
              ajax_chart(json_url, cpuStats, memStats, netStats)
          }, 5000);
          // function to update our chart
          function ajax_chart(url, cpuStats, memStats, netStats) {
              
              $.ajax({
                type: "GET", //GET, POST, PUT
                url: url,  //the url to call
                headers: {
                  "Authorization": "Bearer ' . SessionService::$accessToken . '"
                }
              }).done(function (answer) {
                  //Response ok. Work with the data returned
                  if(answer.success === true){
                  
                    memStats.data.labels = answer.response.labels;
                    memStats.data.datasets[0].data = answer["response"]["data"]["memory"]; 
                    memStats.update();
                
                    cpuStats.data.labels = answer.response.labels;
                    cpuStats.data.datasets[0].data = answer["response"]["data"]["cpu"]; 
                    cpuStats.update();
                    
                  
                    netStats.data.labels = answer.response.labels;
                    netStats.data.datasets[0].data = answer["response"]["data"]["netIn"]; 
                    netStats.data.datasets[1].data = answer["response"]["data"]["netOut"]; 
                    netStats.update();
                  } else {
                      console.log(answer);
                  }
              }).fail(function ()  {});
      
          }
        });
      </script>
    ');

    $args["proxmox"] = $get;
    $args["rdns"] = $rDns;

    TwigService::renderPage('cp/teams/services/proxmox.twig', $args);
  }
}