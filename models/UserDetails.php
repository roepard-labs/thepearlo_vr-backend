<?php
// Requiere el conexion a la base de datos
require_once __DIR__ . '/../core/db.php';

// Clase UserDetails
class UserDetails
{
    private $db;

    // Crea una nueva instancia
    public function __construct()
    {
        $dbConfig = new DBConfig();
        $this->db = $dbConfig->getConnection();
    }

    // Busca un usuario por su ID con género, rol, estado y redes sociales
    public function findById($user_id)
    {
        $sql = "SELECT 
                    u.*,
                    g.gender_name,
                    r.role_name,
                    st.status_name,
                    s.github_username,
                    s.linkedin_username,
                    s.twitter_username,
                    s.discord_tag,
                    s.personal_website,
                    s.show_social_public
                FROM users u
                LEFT JOIN genders g ON u.gender_id = g.gender_id
                LEFT JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN status st ON u.status_id = st.status_id
                LEFT JOIN user_social s ON u.user_id = s.user_id
                WHERE u.user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>