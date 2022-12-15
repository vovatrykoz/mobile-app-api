<?php
require_once "database.php";

class DefaultModel extends Database
{

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
}
