<?php

class User
{
    private $conn;
    private $table = "users";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function all()
    {
        $stmt = $this->conn->query("SELECT id, name, email, role, phone, created_at FROM {$this->table} ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public function findByEmail($email)
    {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function find($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table} (name,email,password,role,phone) VALUES (:name,:email,:password,:role,:phone)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'phone' => $data['phone'],
        ]);
    }

    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table} SET name = :name, email = :email, role = :role, phone = :phone WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'phone' => $data['phone'],
            'id' => $id,
        ]);
    }

    public function updatePassword($id, $newHash)
    {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET password = :pw WHERE id = :id");
        return $stmt->execute(['pw' => $newHash, 'id' => $id]);
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
