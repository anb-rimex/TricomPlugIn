<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class TricomPlugIn extends eqLogic {
    /*     * *************************Attributs****************************** */
    
      
  /*
   * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
   * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
	public static $_widgetPossibility = array();
   */
    
    /*     * ***********************Methode static*************************** */

  	public static function callTricomHttpServer($_url,$_timeout = 5) {
      	if (strpos($_url, '?') !== false) {
			$url = 'http://'. config::byKey('tricomIpAddress', 'TricomPlugIn', '127.0.0.1') .':' . config::byKey('tricomPort', 'TricomPlugIn', 9000) . '/jeedom/' . trim($_url, '/') . '&apikey=' . jeedom::getApiKey();
		} else {
			$url = 'http://'. config::byKey('tricomIpAddress', 'TricomPlugIn', '127.0.0.1') .':' . config::byKey('tricomPort', 'TricomPlugIn', 9000)  . '/jeedom/' . trim($_url, '/') . '?apikey=' . jeedom::getApiKey();
		}
      
       	log::add('TricomPlugIn', 'info', __('URL : ', __FILE__) . $url);
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
		));
		if($_timeout !== null){
			curl_setopt($ch, CURLOPT_TIMEOUT, $_timeout);
		}
		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			$curl_error = curl_error($ch);
       		log::add('TricomPlugIn', 'error', __('ERROR HTTP : ', __FILE__) . $curl_error);
		}
		curl_close($ch);
      
       	log::add('TricomPlugIn', 'info', __('SEND VALUE TO TRICOM : ', __FILE__) . $result);

		return $result;
	}

  
    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
     */
	public static function cron($_eqLogic_id = null) {
        $timestamp = time();
        log::add('TricomPlugIn', 'info', __('TIME START : ', __FILE__) . date("F d, Y h:i:s A", $timestamp));
     
       	$startSecond = date("s", $timestamp) + 1;        
        $startTime = microtime(true);
       	$currentTime = microtime(true);
         
		if ($_eqLogic_id == null) { // La fonction n’a pas d’argument donc on recherche tous les équipements du plugin
			$eqLogics = self::byType('TricomPlugIn', true);
		} else {// La fonction a l’argument id(unique) d’un équipement(eqLogic)
			$eqLogics = array(self::byId($_eqLogic_id));
		}		  

        while(($currentTime - $startTime) < (60-$startSecond)) {
            $contents = TricomPlugIn::callTricomHttpServer('/allExosOutputsValues');
              
           if(is_json($contents, $contents)) {
            $exosValues = json_decode($contents, true);
            log::add('TricomPlugIn', 'info', __('EXO 1-1 VALUE : ', __FILE__) . $exosValues[1][1]);
            foreach ($eqLogics as $tricom) {
                if ($tricom->getIsEnable() == 1) {//vérifie que l'équipement est acitf
                    $cmd = $tricom->getCmd(null, 'EtatCmd');//retourne la commande "refresh si elle exxiste
                    if (!is_object($cmd)) {//Si la commande n'existe pas
                      log::add('TricomPlugIn', 'info', __('CMD EtatCmd Not found...', __FILE__));
                      continue; //continue la boucle
                    }
                    $oldValue=$cmd->execCmd();
                  
                    $exoAddress = $tricom->getConfiguration("exoAddress");		
      				$outputNbr = $tricom->getConfiguration("outputNbr");
                  
                  	if (array_key_exists($exoAddress, $exosValues)) {
                        $values = $exosValues[$exoAddress];
                  		if (array_key_exists($outputNbr, $values)) {
                          $newValue = $values[$outputNbr];
                          if($cmd->getSubType() == 'binary' and $newValue > 0) {
                          	$newValue = 1;
                          }
                          if($newValue != $oldValue) {
							$tricom->checkAndUpdateCmd($cmd,$newValue);
                    		log::add('TricomPlugIn', 'info', __('CMD EtatCmd found... New Value = ', __FILE__) . $newValue . ' ||| OldValue = ' . $oldValue );
                          }
                    }
                  	
     
                  }
                }
            }
           }
            usleep(500000);
       		$currentTime = microtime(true);
        	log::add('TricomPlugIn', 'info','========DIF : ' . ($currentTime - $startTime));
          }   
      	$timestamp = time();
        log::add('TricomPlugIn', 'info', __('TIME END : ', __FILE__) . date("F d, Y h:i:s A", $timestamp));
        
	}
 
  	public static function templateParameters($_template = ''){
		$return = array();
		foreach (ls(dirname(__FILE__) . '/../config/template', '*.json', false, array('files', 'quiet')) as $file) {
			try {
				$content = file_get_contents(dirname(__FILE__) . '/../config/template/' . $file);
				if (is_json($content)) {
					$return += json_decode($content, true);
				}
			} catch (Exception $e) {
				
			}
		}
		if (isset($_template) && $_template != '') {
			if (isset($return[$_template])) {
				return $return[$_template];
			}
			return array();
		}
		return $return;
	}
  
    
    /*
     * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
      public static function cron5() {
      }
     */

    /*
     * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
      public static function cron10() {
      }
     */
    
    /*
     * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
      public static function cron15() {
      }
     */
    
    /*
     * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
      public static function cron30() {
      }
     */
    
    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {
      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {
      }
     */



    /*     * *********************Méthodes d'instance************************* */
    
 // Fonction exécutée automatiquement avant la création de l'équipement 
    public function preInsert() {
        
    }

 // Fonction exécutée automatiquement après la création de l'équipement 
    public function postInsert() {
        
    }

 // Fonction exécutée automatiquement avant la mise à jour de l'équipement 
    public function preUpdate() {
        
    }

 // Fonction exécutée automatiquement après la mise à jour de l'équipement 
    public function postUpdate() {		
      	if ($this->getIsEnable() == 1) {
          $cmd = $this->getCmd(null, 'refresh'); // On recherche la commande refresh de l’équipement
          if (is_object($cmd)) { //elle existe et on lance la commande
               $cmd->execCmd();
          }   
        }
    }

  	public function refresh() {
		try {
			foreach ($this->getCmd('info') as $cmd) {
				if ($cmd->getConfiguration('calcul') == '' || $cmd->getConfiguration('tricomAction', 0) != '0') {
					continue;
				}
				$value = $cmd->execute();
				if ($cmd->execCmd() != $cmd->formatValue($value)) {
					$cmd->event($value);
				}
			}
		} catch (Exception $exc) {
			log::add('virtual', 'error', __('Erreur pour ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $exc->getMessage());
		}
	}
  
 // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement 
    public function preSave() {
		
    }
  
  	public function applyTemplate($_template){
		$template = self::templateParameters($_template);
		if (!is_array($template)) {
			return true;
		}
		$this->import($template);
	}

 // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement 
    public function postSave() {      
        $createRefreshCmd = true;
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = cmd::byEqLogicIdCmdName($this->getId(), __('Rafraichir', __FILE__));
			if (is_object($refresh)) {
				$createRefreshCmd = false;
			}
		}
		if ($createRefreshCmd) {
			if (!is_object($refresh)) {
				$refresh = new TricomPlugInCmd();
				$refresh->setLogicalId('refresh');
				$refresh->setIsVisible(1);
				$refresh->setName(__('Rafraichir', __FILE__));
			}
			$refresh->setType('action');
			$refresh->setSubType('other');
			$refresh->setEqLogic_id($this->getId());
			$refresh->save();
		}
    }
  
   public function sendValueToTricom($value) {
     if ($this->getIsEnable() == 1) {
        $result = "";
      	
       $url = '/exoOutputValue?';
       
       $exoAddress = $this->getConfiguration("exoAddress");
       $url = $url . 'exo=' . $exoAddress;
		
      	$outputNbr = $this->getConfiguration("outputNbr");
        $url = $url . '&output=' . $outputNbr;
       
        $url = $url . '&value=' . $value;
       
       
    	$result=$result . $url . ' ||| ';
   		$result= $result . "Exo Address = " . $exoAddress;
   		$result= $result . " ||| Output Nbr = " . $outputNbr;
   		$result= $result . " ||| Value = " . $value;
       	log::add('TricomPlugIn', 'info', __('SEND VALUE TO TRICOM : ', __FILE__) . $result);
       
       TricomPlugIn::callTricomHttpServer($url);
				
      }
   }
  
    public function getAddressExo() {
      if ($this->getIsEnable() == 1) {
        $result = "";
    	$exoAddress = $this->getConfiguration("exoAddress");
   		$result= $result . "Exo Address = " . $exoAddress;
    	return $result;
      }
      return "";
    }    
  	
  	public function getOutputNbr() {
      if ($this->getIsEnable() == 1) {
        $result = "";
		$outputNbr = $this->getConfiguration("outputNbr");
   		$result= $result . "\nOutput Nbr = " . $outputNbr;
    	return $result;
      }
      return "";
    }
  
   	public function getOutputStatus() {
      if ($this->getIsEnable() == 1) {
        	return "NOT OK";
      }
      return "Off";
    }


 // Fonction exécutée automatiquement avant la suppression de l'équipement 
    public function preRemove() {
        
    }

 // Fonction exécutée automatiquement après la suppression de l'équipement 
    public function postRemove() {
        
    }

    /*
     * Non obligatoire : permet de modifier l'affichage du widget (également utilisable par les commandes)
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire : permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire : permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */

}

class TricomPlugInCmd extends cmd {
    /*     * *************************Attributs****************************** */
    
      	private $requestNbr = "0";
    /*
      public static $_widgetPossibility = array();
    */
  
    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

  public function preSave() {
		if ($this->getLogicalId() == 'refresh') {
			return;
		}
		if ($this->getConfiguration('tricomAction') == 1) {
			$actionInfo = TricomPlugInCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getName());
			if (is_object($actionInfo)) {
				$this->setId($actionInfo->getId());
			}
			if($this->getType() == 'info'){
				$this->setValue('');
			}
		}
		if ($this->getType() == 'action') {
			if ($this->getConfiguration('infoName') == '') {
				throw new Exception(__('Le nom de la commande info ne peut etre vide', __FILE__));
			}
			$cmd = cmd::byId(str_replace('#', '', $this->getConfiguration('infoName')));
			if (is_object($cmd)) {
				if($cmd->getId() == $this->getId()){
					throw new Exception(__('Vous ne pouvez appeller la commande elle meme (boucle infinie) sur : ', __FILE__).$this->getName());
				}
				$this->setSubType($cmd->getSubType());
				$this->setConfiguration('infoId', '');
			} else {
				$actionInfo = TricomPlugInCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getConfiguration('infoName'));
				if (!is_object($actionInfo)) {
					$actionInfo = new TricomPlugInCmd();
					$actionInfo->setType('info');
					switch ($this->getSubType()) {
						case 'slider':
						$actionInfo->setSubType('numeric');
						break;
						default:
						$actionInfo->setSubType('string');
						break;
					}
				}else{
					if($actionInfo->getId() == $this->getId()){
						throw new Exception(__('Vous ne pouvez appeller la commande elle meme (boucle infinie) sur : ', __FILE__).$this->getName());
					}
				}
				$actionInfo->setConfiguration('tricomAction', 1);
				$actionInfo->setName($this->getConfiguration('infoName'));
				$actionInfo->setEqLogic_id($this->getEqLogic_id());
				$actionInfo->save();
				$this->setConfiguration('infoId', $actionInfo->getId());
			}
		} 
	}
  
  
    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
     */
  	public function dontRemoveCmd() {
		if ($this->getLogicalId() == 'refresh') {
			return true;
		}
		return false;
	}

  // Exécution d'une commande  
     public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		if ($this->getLogicalId() == 'refresh') {
			$eqLogic->refresh();
			return;
		}
		switch ($this->getType()) {
			case 'info':
			if ($this->getConfiguration('tricomAction', 0) == '0') {
				try {
					$result = jeedom::evaluateExpression($this->getConfiguration('calcul'));
					if(is_string($result)){
						$result = str_replace('"', '', $result);
					}
					return $result;
				} catch (Exception $e) {
					log::add('TricomPlugIn', 'info', $e->getMessage());
					return $this->getConfiguration('calcul');
				}
			}
			break;
			case 'action':
			$tricomCmd = TricomPlugInCmd::byId($this->getConfiguration('infoId'));
			if (!is_object($tricomCmd)) {
				$cmds = explode('&&', $this->getConfiguration('infoName'));
				if (is_array($cmds)) {
					foreach ($cmds as $cmd_id) {
						$cmd = cmd::byId(str_replace('#', '', $cmd_id));
						if (is_object($cmd)) {
							try {
								$cmd->execCmd($_options);
							} catch (\Exception $e) {
								
							}
						}
					}
					return;
				} else {
					$cmd = cmd::byId(str_replace('#', '', $this->getConfiguration('infoName')));
					return $cmd->execCmd($_options);
				}
			} else {
				if ($tricomCmd->getEqType() != 'TricomPlugIn') {
					throw new Exception(__('La cible de la commande tricom n\'est pas un équipement de type tricom', __FILE__));
				}
				if ($this->getSubType() == 'slider') {
					$value = $_options['slider'];
				} else if ($this->getSubType() == 'color') {
					$value = $_options['color'];
				} else if ($this->getSubType() == 'select') {
					$value = $_options['select'];
				} else {
					$value = $this->getConfiguration('value');
				}
				$result = jeedom::evaluateExpression($value);
				if ($this->getSubtype() == 'message') {
					$result = $_options['title'] . ' ' . $_options['message'];
				}
                $eqLogic->sendValueToTricom($result);
				$eqLogic->checkAndUpdateCmd($tricomCmd,$result);
			}
			break;
		}
     }
  

    /*     * **********************Getteur Setteur*************************** */
 	 public function setRequestNbr($requNbr) {
        $this->requestNbr=$requNbr;
      }
      public function getRequestNbr(){
        return $this->requestNbr;
      }
}


