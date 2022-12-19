<?php
require_once "database.php";

class DefaultModel extends Database
{
    public function checkUser($inParams)
    {
        $outParams = array([
            'paramType' => "s",
            'paramValue' => $inParams['username']
        ],
        [
            'paramType' => "s",
            'paramValue' => $inParams['password']
        ]);

        return $this->select(
            "CALL usp_check_if_user_exists(?, ?);",
            $outParams
        );
    }

    public function insertUser($inParams)
    {
        $outParams = array([
            'paramType' => "s",
            'paramValue' => $inParams['username']
        ],
        [
            'paramType' => "s",
            'paramValue' => $inParams['password']
        ]);

        return $this->select(
            "SELECT insert_new_user(?, ?) AS userId;",
            $outParams
        );
    }

    public function getUserSets($inParams)
    {
        $outParams = array([
            'paramType' => "i",
            'paramValue' => $inParams['userId']
        ]);

        return $this->select(
            "CALL usp_get_user_sets(?);",
            $outParams
        );
    }

    public function getSetOwner($inParams)
    {
        $outParams = array([
            'paramType' => "i",
            'paramValue' => $inParams['setId']
        ]);

        return $this->select(
            "CALL usp_get_set_owner(?);",
            $outParams
        );
    }

    public function getEntriesFromSet($inParams)
    {
        $outParams = array([
            'paramType' => "i",
            'paramValue' => $inParams['setId']
        ]);

        return $this->select(
            "CALL usp_get_entries_from_set(?);",
            $outParams
        );
    }

    public function getAllSets()
    {
        return $this->select(
            "CALL usp_get_all_sets();"
        );
    }

    public function insertSet($inParams)
    {
        $outParams = array([
            'paramType' => "s",
            'paramValue' => $inParams['setName']
        ],
        [
            'paramType' => "s",
            'paramValue' => $inParams['creatorId']
        ]);

        return $this->select(
            "SELECT upload_new_set(?, ?) AS setId;",
            $outParams
        );;
    }

    public function updateSet($inParams)
    {
        $outParams = array([
            'paramType' => "s",
            'paramValue' => $inParams['setName']
        ],
        [
            'paramType' => "i",
            'paramValue' => $inParams['setId']
        ]);

        return $this->delete(
            "CALL usp_update_set(?, ?);",
            $outParams
        );
    }

    public function deleteSet($inParams)
    {
        $outParams = array([
            'paramType' => "i",
            'paramValue' => $inParams['setId']
        ]);

        return $this->delete(
            "CALL usp_delete_set(?);",
            $outParams
        );
    }

    public function insertEntryIntoSet($inParams)
    {
        $outParams = array([
            'paramType' => "s",
            'paramValue' => $inParams['entryTerm']
        ],
        [
            'paramType' => "s",
            'paramValue' => $inParams['entryDefinition']
        ],
        [
            'paramType' => "i",
            'paramValue' => $inParams['setId']
        ]);

        return $this->insert(
            "CALL usp_insert_entry_into_set(?, ?, ?);",
            $outParams
        );
    }

    public function updateEntry($inParams)
    {
        $outParams = array([
            'paramType' => "i",
            'paramValue' => $inParams['entryId']
        ],
        [
            'paramType' => "s",
            'paramValue' => $inParams['entryTerm']
        ],
        [
            'paramType' => "s",
            'paramValue' => $inParams['entryDefinition']
        ],);

        return $this->delete(
            "CALL usp_update_entry(?, ?, ?);",
            $outParams
        );
    }

    public function deleteEntryFromSet($inParams)
    {
        $outParams = array([
            'paramType' => "i",
            'paramValue' => $inParams['entryId']
        ]);

        return $this->delete(
            "CALL usp_delete_entry_from_set(?);",
            $outParams
        );
    }
}
