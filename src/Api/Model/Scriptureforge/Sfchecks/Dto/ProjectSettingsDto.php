<?php

namespace Api\Model\Scriptureforge\Sfchecks\Dto;

use Api\Model\Scriptureforge\Sfchecks\SfchecksProjectModel;
use Api\Model\Scriptureforge\Sfchecks\TextListModel;
use Api\Model\Scriptureforge\Sfchecks\TextModel;
use Api\Model\Shared\Dto\RightsHelper;
use Api\Model\Shared\Mapper\JsonEncoder;
use Api\Model\Shared\UserModel;

class ProjectSettingsDto
{
    /**
     * @param string $projectId
     * @param string $userId
     * @returns array - the DTO array
     */
    public static function encode($projectId, $userId)
    {
        $userModel = new UserModel($userId);
        $projectModel = new SfchecksProjectModel($projectId);
        $textList = new TextListModel($projectModel);
        $textList->read();

        $list = $projectModel->listUsers();
        $data = array();

        $data['count'] = count($list->entries);
        $data['entries'] = array_values($list->entries);    // re-index array
        $data['project'] = ProjectSettingsDtoEncoder::encode($projectModel);
        unset($data['project']['users']);

        $data['archivedTexts'] = array();
        foreach ($textList->entries as $entry) {
            $textModel = new TextModel($projectModel, $entry['id']);
            if ($textModel->isArchived) {
                $questionList = $textModel->listQuestionsWithAnswers();
                // Just want count of questions and responses, not whole list
                $entry['questionCount'] = $questionList->count;
                $responseCount = 0; // "Responses" = answers + comments
                foreach ($questionList->entries as $q) {
                    foreach ($q['answers'] as $a) {
                        $commentCount = count($a['comments']);
                        $responseCount += ($commentCount + 1); // +1 for this answer
                    }
                }
                $entry['responseCount'] = $responseCount;
                $entry['dateModified'] = $textModel->dateModified->asDateTimeInterface()->format(\DateTime::RFC2822);

                $data['archivedTexts'][] = $entry;
            }
        }

        $data['rights'] = RightsHelper::encode($userModel, $projectModel);
        $data['bcs'] = BreadCrumbHelper::encode('settings', $projectModel, null, null);

        return $data;
    }
}

class ProjectSettingsDtoEncoder extends JsonEncoder
{
    public function encodeIdReference(&$key, $model)
    {
        if ($key == 'ownerRef') {
            $user = new UserModel();
            if ($user->readIfExists($model->asString())) {
                return [
                    'id' => $user->id->asString(),
                    'username' => $user->username
                ];
            } else {
                return '';
            }
        } else {
            return $model->asString();
        }
    }

    public static function encode($model)
    {
        $encoder = new ProjectSettingsDtoEncoder();
        $data = $encoder->_encode($model);
        if (method_exists($model, 'getPrivateProperties')) {
            $privateProperties = (array) $model->getPrivateProperties();
            foreach ($privateProperties as $prop) {
                unset($data[$prop]);
            }
        }

        return $data;
    }
}
