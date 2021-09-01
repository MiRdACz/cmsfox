<?php
namespace App\Model;

use Nette;
use Nette\Database\Table\Selection;


class UsersManager
{
    use Nette\SmartObject;

    /** @var Nette\Database\Context */
    private $database;


    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }
    public function getUsers()
    {
        return $this->database->table('users');
    }
    public function getUsersOrder($role,$data,$name)
    {
        if($role){
            return $this->database->table('users')->order('role = ? DESC', $role);
        }
        if($name){
            return $this->database->table('users')->order('username ASC');
        }
        if($data){
            if($data == 'true'){
                $data = true;
            }else{$data = false;}

            return $this->database->query('SELECT * FROM users ORDER BY', [
                'id' => $data,
            ]);
        }
    }
    public function getUsersId($id)
    {
        return $this->database->table('users')->get($id);
    }
    public function deleteUsersId($id)
    {
        $this->database->beginTransaction();
        try {
            $this->database->table('users')->where('id', $id)->delete();
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e; // pošlu to dál
        }
    }

    public function findPublishedUsers($role,$date,$name): Nette\Database\Table\Selection
	{
        if($role){
            return $this->database->table('users')->order('role = ? DESC', $role);
        }
        if($name){
            return $this->database->table('users')->order('username ASC');
        }
        if($date){
            if($date == 'true'){
                return $this->database->table('users')->order('id DESC');
            }else{ return $this->database->table('users')->order('id ASC'); }
        }
		return $this->database->table('users')->order('id DESC');
	}
}