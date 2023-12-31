<?php
require_once('initBDD.php');

//Validation de connection pour utilisateur deja existant..
if(isset($_POST['inputUser']) && isset($_POST['inputPassword'])){
   if(!isset($_SESSION['userActive'])){
       foreach($users as $user){
           if($user['userName'] == $_POST['inputUser'] && password_verify($_POST['inputPassword'], $user['userKey'])){
                  $_SESSION['userActive'] = $user['userName'];
           }
       }   
   }
    
   //Validation du choix ou creation de session..
   if(!isset($_SESSION['sessionActive'])){
       if(!empty($_POST['AccountActiveForSessionActive']) && !empty($_POST['AccountActiveForNewSession'])){
              $_SESSION['errorDoubleInput'] = 'Vous ne pouvez pas vous connectez et créer une session en même temps.';
       }else if(!empty($_POST['AccountActiveForSessionActive'])){
              foreach($sessionsUsers as $validSession){
                     if($validSession['sessionKey'] == $_POST['AccountActiveForSessionActive']){
                            $_SESSION['sessionActive'] = $validSession['sessionKey'];
                            $_SESSION['nameModerator'] = $validSession['UserSession'];
                     }
              }
       }else if(!empty($_POST['AccountActiveForNewSession'])){
              foreach($sessionsUsers as $validSession){
                     if($validSession['sessionKey'] == $_POST['AccountActiveForNewSession']){
                            $_SESSION['errorSessionIsActive'] = "La session existe déjà.";
                     }
              }
              $accountActiveForNewSession = validInput($_POST['AccountActiveForNewSession']);
              if(!isset($_SESSION['errorSessionIsActive']) && !empty($accountActiveForNewSession) && isset($_SESSION['userActive'])){
                     $_SESSION['sessionActive'] = $accountActiveForNewSession;
                     $_SESSION['nameModerator'] = $_SESSION['userActive'];

                     $sessionAttri = $mySqlConnection->prepare('INSERT INTO `attributions` (`userName`, `sessionKey`) VALUES (:userName , :sessionKey)');
                     $sessionAttri->execute([
                            'userName' => $_SESSION['userActive'],
                            'sessionKey' => $accountActiveForNewSession
                     ]);
                     $sessionAttri->fetchAll();

                     $addNewSession = $mySqlConnection -> prepare('INSERT INTO `sessionsusers`(`sessionKey`, `UserSession`) VALUES (:sessionKey, :UserSession)');
                     $addNewSession -> execute([
                            'sessionKey' => $accountActiveForNewSession,
                            'UserSession' => $_SESSION['userActive']
                     ]);
                     $addNewSession -> fetchAll();
              }
       }
       
   }
}

//Validation de connexion pour une creation de compte..
if(isset($_POST['inputCrtUser'])){
       $inputCrtUser = validInput($_POST['inputCrtUser']);
       if(empty($inputCrtUser)){
              $_SESSION['errorCrtUser'] = 'Votre pseudo contient des caractères inutilisables';
       }
}

if(isset($_POST['inputCrtPassword'])){
       $inputCrtPassword = validInput($_POST['inputCrtPassword']);
       if(empty($inputCrtPassword)){
              $_SESSION['errorCrtPassword'] = 'Votre mot de passe contient des caractères inutilisables';
       }
}

if(isset($inputCrtUser) && isset($inputCrtPassword) && !empty($inputCrtUser) && !empty($inputCrtPassword)){
       
       foreach($users as $user){
           if($user['userName'] == $_POST['inputCrtUser']){
                  $_SESSION['errorUserAlreadyExists'] = "Ce nom d'utilisateur est déjà existant.";
           }
       }   
       
       if(!isset($_SESSION['errorUserAlreadyExists'])){
              $_SESSION['placeholderPseudo'] = $inputCrtUser;
              $_SESSION['placeHolderpass'] = $inputCrtPassword;
              $addUser = $mySqlConnection -> prepare('INSERT INTO `users`(`userName`, `userKey`) VALUES (:userName, :userKey)');
              $addUser -> execute([
                     'userName' => $inputCrtUser,
                     'userKey' => password_hash($inputCrtPassword, PASSWORD_DEFAULT)
              ]);
       }
}

    
if(isset($_POST['inputUser']) && !isset($_SESSION['userActive'])){
       if($_SERVER['PHP_SELF'] == '/ma-liste/index.php'){
              $_SESSION['errorUser'] = 'Il y a une erreur avec le nom d\'utilisateur ou le MDP.';
       }
       
}

if(isset($_POST['AccountActiveForSessionActive']) && !isset($_SESSION['sessionActive'])){
       if(!isset($_SESSION['errorDoubleInput'])){
              if($_SERVER['PHP_SELF'] == '/ma-liste/index.php'){
                     $_SESSION['errorSession'] = 'Il y a une erreur avec le nom de session.';
              }
       }
}


//Info attributions

if(isset($_SESSION['userActive']) && isset($_SESSION['sessionActive'])){
       
       $sessionAttri = $mySqlConnection->prepare('SELECT `sessionKey` FROM attributions WHERE userName = (:userName)');
       $sessionAttri->execute([
              'userName' => $_SESSION['userActive']
       ]);
       $Attributions = $sessionAttri->fetchAll();

       $validAccess = false;
foreach($Attributions as $isValidAttribution){
       if($isValidAttribution['sessionKey'] == $_SESSION['sessionActive']){
              $validAccess = true;
       };
}

       if(!$validAccess){
              unset($_SESSION['userActive']);
              unset($_SESSION['sessionActive']);
              $_SESSION['errorAccess'] = 'Vous n\'avez pas les droits d\'accès à la session choisie';
       }

}

//Retirer une attribution..

if(isset($_POST['delFormSpec'])){
       $delSpec = $mySqlConnection->prepare('DELETE FROM `attributions` WHERE `userName` = :userName AND `sessionKey` = :sessionKey');
       $delSpec->execute([
              'userName' => validInput($_POST['delFormSpec']),
              'sessionKey' => $_SESSION['sessionActive']
       ]);
}

//Ajouter attribution..

if(isset($_POST['addFromSpec'])){

      $newSpec = validInput($_POST['addFromSpec']);

      $reelUser = false;
       foreach($users as $user){

              if($user['userName'] == $newSpec){

                     $reelUser = true;

                     $attrib = $mySqlConnection->prepare('SELECT `userName` FROM `attributions` WHERE sessionKey = (:sessionKey) ORDER BY `userName`');
                     $attrib->execute([
                         'sessionKey' => $_SESSION['sessionActive']
                     ]);
                     $Specs = $attrib->fetchAll(PDO::FETCH_ASSOC);

                     
                     $attributionAlreadyExist = false;
                     foreach($Specs as $spec){
                            if($spec['userName'] == $newSpec){
                                   $attributionAlreadyExist = true;
                            }
                     }
                     if(!$attributionAlreadyExist){
                            $newAttribution = $mySqlConnection->prepare('INSERT INTO `attributions` (`userName`, `sessionKey`) VALUES (:userName, :sessionKey)');
                            $newAttribution->execute([
                                   'userName' => $newSpec,
                                   'sessionKey' => $_SESSION['sessionActive']
                            ]);
                     }//Afficher maintenant les messages d'erreurs..
                     
                     if($attributionAlreadyExist){
                            $errorDoublon = 'Ce spectateur se trouve déjà dans la liste';
                     }
              }
       }

       if(!$reelUser){
              $uncaughtUser = 'Ce pseudo n\'existe pas';
       }
}



//____________________________________Envoi mail au support______________________________________//

if(isset($_POST['emailForSupport']) && isset($_POST['messageForSupport'])){

       $email = validInput($_POST['emailForSupport']);
       $message = validInput($_POST['messageForSupport']);

       if(!empty($email) && !empty($message)){
              $to = 'arthur58230@hotmail.fr';
              $subject = 'Support de "Ma liste"';
              $message = $message;
              $header = 'From: '. $email . '\r\n';
              mail($to, $subject, $message, $header);
       }else{
              $_SESSION['errorMessageSupport'] = 'Vous tentez d\'envoyer des informations inexploitables'; 
       };
}



function validInput($param){
    $param = trim($param);
    $param = strip_tags($param);
    $param = htmlspecialchars($param);
    return $param;
}
?>