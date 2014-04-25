<?php

namespace models\languageforge\lexicon\commands;

use libraries\lfdictionary\common\UserActionDeniedException;
use libraries\shared\palaso\CodeGuard;
use models\languageforge\lexicon\config\LexConfiguration;
use models\languageforge\lexicon\config\LexiconFieldListConfigObj;
use models\languageforge\lexicon\LexiconProjectModel;
use models\languageforge\lexicon\LexEntryModel;
use models\languageforge\lexicon\LiftImport;
use models\languageforge\lexicon\LiftMergeRule;
use models\commands\ActivityCommands;
use models\commands\ProjectCommands;
use models\mapper\ArrayOf;
use models\mapper\MapOf;
use models\mapper\JsonEncoder;
use models\mapper\JsonDecoder;
use models\mapper\MongoStore;
use models\rights\Domain;
use models\rights\Operation;
use models\rights\Roles;
use models\shared\dto\RightsHelper;
use models\UserModel;

class LexProjectCommands {

	public static function updateConfig($projectId, $config) {
		$project = new LexiconProjectModel($projectId);
		$configModel = new LexConfiguration();
		JsonDecoder::decode($configModel, $config);
		$project->config = $configModel;
		$decoder = new JsonDecoder();
		$decoder->decodeMapOf('', $project->inputSystems, $config['inputSystems']);
		$project->write();
	}
	
	/**
	 * Create or update project
	 * @param array<projectModel> $projectJson
	 * @param string $userId
	 * @throws UserUnauthorizedException
	 * @throws \Exception
	 * @return string projectId
	 */
	public static function updateProject($projectJson, $userId) {
		$project = new LexiconProjectModel();
		$id = $projectJson['id'];
		$isNewProject = ($id == '');
		$oldDBName = '';
		if ($isNewProject) {
			if (!RightsHelper::userHasSiteRight($userId, Domain::PROJECTS + Operation::EDIT)) {
				throw new UserUnauthorizedException("Insufficient privileges to create new project in method 'updateProject'");
			}
		} else {
			if (!RightsHelper::userHasProjectRight($id, $userId, Domain::USERS + Operation::EDIT)) {
				throw new UserUnauthorizedException("Insufficient privileges to update project in method 'updateProject'");
			}
			$project->read($id);
			$oldDBName = $project->databaseName();
		}
		JsonDecoder::decode($project, $projectJson);
		$newDBName = $project->databaseName();
		if (($oldDBName != '') && ($oldDBName != $newDBName)) {
			if (MongoStore::hasDB($newDBName)) {
				throw new \Exception("New project name " . $projectJson->projectname . " already exists. Not renaming.");
			}
			MongoStore::renameDB($oldDBName, $newDBName);
		}
		$projectId = $project->write();
		if ($isNewProject) {
			ProjectCommands::updateUserRole($projectId, array('id' => $userId, 'role' => Roles::PROJECT_ADMIN));
		}
		return $projectId;
	}
	
	/**
	 * @param string $id
	 * @param string $authUserId - the admin user's id performing the update (for auth purposes)
	 */
	public static function readProject($id) {
		$project = new LexiconProjectModel($id);
		return JsonEncoder::encode($project);
	}
	
	// TODO Enhance. Add preview of import. Would minimally include metrics of import. IJH 2014-03
	
	public static function importLift($projectId, $import) {
		$allowedExtensions = array(".lift");
		
		// LIFT file
		$base64data = substr($import['file']['data'], strpos($import['file']['data'], 'base64,')+7);
		$liftXml = base64_decode($base64data);
		
		// LIFT file name
		$fileName = str_replace(array('/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'), '_', $import['file']['name']);	// replace special characters with _
		$fileExt = (false === $pos = strrpos($fileName, '.')) ? '' : substr($fileName, $pos);
		if (! in_array($fileExt, $allowedExtensions)) {
			$allowedExtensionsList = "*" . implode(", *", $allowedExtensions);
			$message = "$fileName is not an allowed LIFT file. Ensure the file is one of the following types: $allowedExtensionsList.";
			if (count($allowedExtensions) == 1) {
				$message = "$fileName is not an allowed LIFT file. Ensure it is a $allowedExtensionsList file.";
			}
			throw new \Exception($message);
		}
		
		// make the Assets folder if it doesn't exist
		$project = new LexiconProjectModel($projectId);
		$folderPath = $project->getAssetsFolderPath();
		if (!file_exists($folderPath) and !is_dir($folderPath)) {
			mkdir($folderPath, 0777, true);
		};
		
		LiftImport::merge($liftXml, $project, $import['settings']['mergeRule'], $import['settings']['skipSameModTime'], $import['settings']['deleteMatchingEntry']);
		
		if (!$project->liftFilePath || $import['settings']['mergeRule'] != LiftMergeRule::IMPORT_LOSES) {
			// cleanup previous files of any allowed extension
			$cleanupFiles = glob($folderPath . '/*[' . implode(', ', $allowedExtensions) . ']');
			foreach ($cleanupFiles as $cleanupFile) {
				@unlink($cleanupFile);
			}
			
			// put the LIFT file into Assets
			$filePath =  $folderPath . '/' . $fileName;
			$moveOk = file_put_contents($filePath, $liftXml);
			
			// update database with file location
			$project->liftFilePath = '';
			if ($moveOk) {
				$project->liftFilePath = $filePath;
			}
		}
		
		$project->write();
	}
	
}

?>