<?php
// admin_panel/avaliacoes.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB

$message = '';
$message_type = '';

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$edit_avaliacao = null;
$is_editing_avaliacao = false;

// ==========================================================
// LÓGICA CRUD PARA AVALIAÇÕES
// ==========================================================

// 1A. ADICIONAR ou ATUALIZAR AVALIAÇÃO (Manual do Admin)
// Bloco de código atualizado para incluir upload de arquivo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_avaliacao'])) {
    $produto_id = (int)$_POST['produto_id'];
    $nome_avaliador = trim($_POST['nome_avaliador']);
    $classificacao = (int)$_POST['classificacao'];
    $comentario = trim($_POST['comentario']);
    
    // --- LÓGICA DE UPLOAD DA FOTO DA AVALIAÇÃO ---
    // Pega a URL existente (se estiver editando) como padrão
    $foto_url = isset($_POST['foto_url_existente']) ? trim($_POST['foto_url_existente']) : null;

    // Verifica se um novo arquivo foi enviado
    if (isset($_FILES['foto_arquivo']) && $_FILES['foto_arquivo']['error'] == 0) {
        $upload_dir = '../uploads/reviews/'; // Pasta de upload
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $nome_arquivo = $_FILES['foto_arquivo']['name'];
        $extensao = pathinfo($nome_arquivo, PATHINFO_EXTENSION);
        $nome_seguro = uniqid('review_admin_' . $produto_id . '_') . '.' . htmlspecialchars(strtolower($extensao));
        $caminho_completo = $upload_dir . $nome_seguro;

        // Validação de tipo de imagem
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($extensao), $tipos_permitidos)) {
            if (move_uploaded_file($_FILES['foto_arquivo']['tmp_name'], $caminho_completo)) {
                // Sucesso! Define a nova URL para salvar no banco
                $foto_url = 'uploads/reviews/' . $nome_seguro;
            } else {
                $message .= " Erro ao mover o arquivo de foto da avaliação."; $message_type = "error";
            }
        } else {
            $message .= " Tipo de arquivo de foto não permitido (Use: jpg, jpeg, png, gif, webp)."; $message_type = "error";
        }
    }
    // --- FIM DA LÓGICA DE UPLOAD ---

    // Aprovado é sempre TRUE se o checkbox estiver marcado (ou se o admin marcar/desmarcar na edição)
    $aprovado = isset($_POST['aprovado']);
    $avaliacao_id = isset($_POST['avaliacao_id']) ? (int)$_POST['avaliacao_id'] : null;

    if (empty($produto_id) || empty($nome_avaliador) || $classificacao < 1 || $classificacao > 5) {
        $message = "Produto, Nome do Avaliador e Classificação (1-5) são obrigatórios.";
        $message_type = "error";
    } elseif ($message_type !== "error") { // Só executa se o upload (agora) não deu erro
        try {
            if ($avaliacao_id) { // UPDATE
                $sql = "UPDATE avaliacoes_produto SET
                            produto_id = :pid,
                            nome_avaliador = :nome,
                            classificacao = :classificacao,
                            comentario = :comentario,
                            foto_url = :foto_url,
                            aprovado = :aprovado,
                            data_avaliacao = NOW()
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'pid' => $produto_id,
                    'nome' => $nome_avaliador,
                    'classificacao' => $classificacao,
                    'comentario' => $comentario,
                    'foto_url' => !empty($foto_url) ? $foto_url : null,
                    'aprovado' => $aprovado,
                    'id' => $avaliacao_id
                ]);
                $message = "Avaliação atualizada com sucesso!";
                $message_type = "success";
            } else { // INSERT
                $sql = "INSERT INTO avaliacoes_produto (produto_id, nome_avaliador, classificacao, comentario, foto_url, aprovado, usuario_id, data_avaliacao)
                        VALUES (:pid, :nome, :classificacao, :comentario, :foto_url, :aprovado, NULL, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'pid' => $produto_id,
                    'nome' => $nome_avaliador,
                    'classificacao' => $classificacao,
                    'comentario' => $comentario,
                    'foto_url' => !empty($foto_url) ? $foto_url : null,
                    'aprovado' => $aprovado
                ]);
                $message = "Avaliação adicionada com sucesso!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Erro ao salvar avaliação: " . $e->getMessage();
            $message_type = "error";
        }
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $message_type;
    header("Location: avaliacoes.php");
    exit();
}


// 2A. APROVAR/REPROVAR (Ação rápida da lista)
if (isset($_GET['toggle_aprovado'])) {
    $id = (int)$_GET['toggle_aprovado'];
    $status = (int)$_GET['status']; // 0 para reprovar, 1 para aprovar
    $new_status = ($status == 1) ? true : false;

    try {
        $sql = "UPDATE avaliacoes_produto SET aprovado = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['status' => $new_status, 'id' => $id]);
        $message = $new_status ? "Avaliação Aprovada!" : "Avaliação Reprovada!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Erro ao atualizar status: " . $e->getMessage();
        $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $message_type;
    header("Location: avaliacoes.php"); exit;
}

// 3A. DELETAR
if (isset($_GET['delete_avaliacao'])) {
    $id = (int)$_GET['delete_avaliacao'];
    try {
        $sql = "DELETE FROM avaliacoes_produto WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $message = "Avaliação removida!"; $message_type = "success";
    } catch (PDOException $e) {
        $message = "Erro ao remover avaliação: " . $e->getMessage(); $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $message_type;
    header("Location: avaliacoes.php"); exit;
}


// ==========================================================
// LÓGICA DE LEITURA
// ==========================================================

// Pega mensagens flash da sessão
if (isset($_SESSION['flash_message']) && empty($message)) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_message_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);
}

// 4A. MODO DE EDIÇÃO (Carrega dados para o formulário)
if (isset($_GET['edit_avaliacao'])) {
    $id = (int)$_GET['edit_avaliacao'];
    $stmt = $pdo->prepare("SELECT * FROM avaliacoes_produto WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $edit_avaliacao = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_avaliacao) {
        $is_editing_avaliacao = true;
    } else {
        if (empty($message)) {
            $message = "Avaliação não encontrada para edição."; $message_type = "warning";
        }
    }
}

// --- LEITURA DE DADOS PARA EXIBIÇÃO ---
try {
    // 1. Ler Avaliações Pendentes
    $stmt_pendentes = $pdo->query("
        SELECT a.*, p.nome as produto_nome, u.nome as usuario_nome
        FROM avaliacoes_produto a
        JOIN produtos p ON a.produto_id = p.id
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE a.aprovado = false
        ORDER BY a.data_avaliacao ASC
    ");
    $avaliacoes_pendentes = $stmt_pendentes->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ler Avaliações Aprovadas (com paginação)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 15;
    $offset = ($page - 1) * $limit;

    // Contar total de aprovadas
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM avaliacoes_produto WHERE aprovado = true");
    $total_aprovadas = $total_stmt->fetchColumn();
    $total_pages = ceil($total_aprovadas / $limit);

    $stmt_aprovadas = $pdo->prepare("
        SELECT a.*, p.nome as produto_nome, u.nome as usuario_nome
        FROM avaliacoes_produto a
        JOIN produtos p ON a.produto_id = p.id
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE a.aprovado = true
        ORDER BY a.data_avaliacao DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt_aprovadas->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt_aprovadas->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_aprovadas->execute();
    $avaliacoes_aprovadas = $stmt_aprovadas->fetchAll(PDO::FETCH_ASSOC);

    // 3. Ler lista de produtos (para o dropdown do form)
    $stmt_produtos = $pdo->query("SELECT id, nome FROM produtos ORDER BY nome ASC");
    $all_produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    if (empty($message)) {
        $message .= " Erro ao carregar dados: " . $e->getMessage();
        $message_type = "error";
    }
    $avaliacoes_pendentes = [];
    $avaliacoes_aprovadas = [];
    $all_produtos = [];
    $total_pages = 1; $page = 1;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderar Avaliações - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
           CSS COMPLETO DO PAINEL ADMIN (Base configlayout.php)
           + Estilos específicos de avaliacoes.php
           ========================================================== */
        :root {
            --primary-color: #4a69bd; --secondary-color: #6a89cc; --text-color: #f9fafb;
            --light-text-color: #9ca3af; --border-color: rgba(255, 255, 255, 0.1); --background-color: #111827;
            --sidebar-color: #1f2937; --glass-background: rgba(31, 41, 55, 0.7);
            --success-bg: rgba(40, 167, 69, 0.3); --success-text: #c3e6cb;
            --error-bg: rgba(220, 53, 69, 0.3); --error-text: #f5c6cb;
            --info-bg: rgba(0, 123, 255, 0.2); --info-text: #bee5eb;
            --warning-bg: rgba(255, 193, 7, 0.2); --warning-text: #ffeeba;
            --danger-color: #e74c3c; --sidebar-width: 240px; --border-radius: 8px;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); color: var(--text-color); display: flex; min-height: 100vh; overflow-x: hidden; }
        #particles-js { position: fixed; width: 100%; height: 100%; top: 0; left: 0; z-index: -1; opacity: 0.6; }
        a { color: var(--primary-color); text-decoration: none; transition: color 0.2s ease;} a:hover { color: var(--secondary-color); text-decoration: underline;}

        /* --- Sidebar --- */
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-color); height: 100vh; position: fixed; left: 0; top: 0; padding: 1.5rem; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); z-index: 1000; transition: transform 0.3s ease; box-shadow: var(--box-shadow); }
        .sidebar .logo-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; } .sidebar .logo-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 0 10px rgba(74, 105, 189, 0.6); overflow: hidden; background-color: #fff; } .sidebar .logo-circle svg { color: var(--primary-color); width: 24px; height: 24px; } .sidebar .logo-text { font-size: 1rem; font-weight: 600; color: var(--text-color); text-align: center; } .sidebar .divider { width: 100%; height: 1px; background-color: var(--border-color); margin: 1rem 0; } .sidebar nav { flex-grow: 1; } .sidebar nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: var(--light-text-color); text-decoration: none; border-radius: var(--border-radius); margin-bottom: 0.5rem; transition: all 0.3s ease; border: 1px solid transparent; background-color: transparent; } .sidebar nav a:hover, .sidebar nav a.active { background-color: var(--glass-background); color: var(--text-color); border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(74, 105, 189, 0.4); } .sidebar nav a svg { width: 20px; height: 20px; flex-shrink: 0; } .user-profile { position: relative; margin-top: auto; background-color: var(--glass-background); padding: 0.75rem; border-radius: var(--border-radius); display: flex; align-items: center; gap: 1rem; cursor: pointer; border: 1px solid var(--border-color); transition: all 0.3s ease; } .user-profile:hover { border-color: var(--primary-color); } .avatar { width: 35px; height: 35px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; } .user-info .user-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; } .user-info .user-level { font-size: 0.7rem; color: var(--light-text-color); } .profile-dropdown { position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%; background-color: #2c3a4f; border-radius: var(--border-radius); border: 1px solid var(--border-color); padding: 0.5rem; z-index: 20; visibility: hidden; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; } .profile-dropdown.show { visibility: visible; opacity: 1; transform: translateY(0); } .profile-dropdown a { display: flex; gap: 0.75rem; padding: 0.75rem; color: var(--light-text-color); font-size: 0.85rem; border-radius: 6px; } .profile-dropdown a:hover { background-color: var(--glass-background); color: var(--text-color); }
        .sidebar nav .sidebar-submenu { padding-left: 20px; margin-top: -5px; margin-bottom: 5px; overflow: hidden; transition: max-height 0.3s ease-out; max-height: 0; } .sidebar nav .sidebar-submenu.open { max-height: 500px; } .sidebar nav a.has-children { display: flex; justify-content: space-between; align-items: center; } .sidebar nav a .menu-chevron { width: 16px; height: 16px; color: var(--light-text-color); transition: transform 0.3s ease; } .sidebar nav a.open .menu-chevron { transform: rotate(90deg); } .sidebar-submenu a { font-size: 0.9em; padding: 0.7rem 1rem 0.7rem 1.5rem; color: var(--light-text-color); position: relative; } .sidebar-submenu a::before { content: ''; position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); width: 4px; height: 4px; border-radius: 50%; background-color: var(--light-text-color); transition: all 0.3s ease; } .sidebar-submenu a:hover { color: var(--text-color); background-color: transparent; border-color: transparent; box-shadow: none; } .sidebar-submenu a:hover::before { background-color: var(--primary-color); } .sidebar-submenu a.active-child { color: #fff; font-weight: 600; } .sidebar-submenu a.active-child::before { background-color: var(--primary-color); transform: translateY(-50%) scale(1.5); }


        /* --- Conteúdo Principal --- */
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 2rem 2.5rem; min-height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease; width: calc(100% - var(--sidebar-width)); }
        .content-header { margin-bottom: 2rem; background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); backdrop-filter: blur(5px); }
        .content-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0 0 0.25rem 0; }
        .content-header p { font-size: 1rem; color: var(--light-text-color); margin: 0; }

        /* --- CSS PARA ABAS (TABS) --- */
        .tab-navigation { display: flex; gap: 0.5rem; margin-bottom: -1px; position: relative; z-index: 10; padding-left: 1rem; }
        .tab-button { padding: 0.8rem 1.5rem; border: 1px solid var(--border-color); background-color: var(--sidebar-color); color: var(--light-text-color); border-radius: var(--border-radius) var(--border-radius) 0 0; cursor: pointer; font-weight: 500; transition: all 0.2s ease-in-out; border-bottom-color: var(--border-color); }
        .tab-button:hover { background-color: var(--glass-background); color: var(--text-color); }
        .tab-button.active { background-color: var(--glass-background); color: var(--primary-color); font-weight: 600; border-bottom-color: var(--glass-background); box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        .tab-pane { display: none; padding: 0; background: transparent; }
        .tab-pane.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- Seções CRUD --- */
        .crud-section { margin-bottom: 2.5rem; }
        .tab-pane .crud-section:last-child { margin-bottom: 0; }
        .crud-section h3 { font-size: 1.25rem; color: var(--text-color); margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600; }
        .form-container, .list-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }
        .tab-pane .crud-section:first-child .form-container,
        .tab-pane .crud-section:first-child .list-container {
             border-top-left-radius: 0;
             border-top-right-radius: 0;
        }
        .form-container h4 { font-size: 1.1rem; color: var(--primary-color); margin-bottom: 1.25rem; font-weight: 600; }

        /* --- Formulários --- */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--light-text-color); font-size: 0.8rem; text-transform: uppercase; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="number"], .form-group textarea, .form-group select, .form-group input[type="file"] { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.3); color: var(--text-color); box-sizing: border-box; transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9em; }
        .form-group input[type="file"] { padding: 0.5rem 0.8rem; line-height: 1.5; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group-check { display: flex; align-items: center; padding-top: 0.5rem; }
        .form-group-check label { font-weight: 400; color: var(--text-color); display: inline; margin-left: 0.5rem; text-transform: none; }
        .form-group-check input[type="checkbox"] { width: auto; vertical-align: middle; accent-color: var(--primary-color); cursor: pointer;}
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }
        button[type="submit"] { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: background-color 0.3s ease, transform 0.1s ease; font-size: 0.9em; }
        button[type="submit"]:hover { background-color: var(--secondary-color); transform: translateY(-1px); }
        button[type="submit"].update { background-color: #28a745; }
        button[type="submit"].update:hover { background-color: #218838; }
        .form-container a.cancel { color: var(--light-text-color); margin-left: 1rem; font-size: 0.9em; }
        .form-container a.cancel:hover { text-decoration: underline; }

        /* --- Tabelas (Listagem) --- */
        .list-container { overflow-x: auto; }
        .list-container table { width: 100%; border-collapse: collapse; background-color: transparent; border-radius: 0; overflow: hidden; font-size: 0.85em; border: none; min-width: 600px; }
        .list-container th, .list-container td { border-bottom: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: middle; }
        .list-container th { background-color: rgba(0, 0, 0, 0.4); color: var(--text-color); font-weight: 600; text-transform: uppercase; font-size: 0.75em; }
        .list-container tbody tr:last-child td { border-bottom: none; }
        .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .list-container .actions { white-space: nowrap; text-align: right; }
        .list-container .actions a { color: var(--primary-color); margin-left: 0.8rem; font-size: 0.85em; transition: color 0.2s ease; }
        .list-container .actions a:last-child { margin-right: 0; }
        .list-container .actions a:hover { color: var(--secondary-color); }
        .list-container .actions a.delete { color: var(--danger-color); }
        .list-container .actions a.delete:hover { color: #c0392b; }
        .list-container .actions a.approve { color: #82e0aa; }
        .list-container .actions a.approve:hover { color: #5dbb88; }
        .list-container .actions a.reprove { color: #ffc107; }
        .list-container .actions a.reprove:hover { color: #e0a800; }
        .status-aprovado { color: #82e0aa; font-weight: bold; }
        .status-pendente { color: #ffc107; font-weight: bold; }

        /* --- Estilos específicos Avaliações --- */
        .avaliacao-comentario {
            max-width: 350px;
            white-space: normal;
            font-size: 0.9em;
            color: var(--light-text-color);
            line-height: 1.5;
        }
        .avaliacao-foto {
            max-width: 80px;
            max-height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .avaliacao-foto:hover { transform: scale(3); z-index: 100; position: relative; }
        .avaliacao-rating { color: #ffc107; font-weight: 600; white-space: nowrap; }

        /* --- Paginação --- */
        .pagination { display: flex; justify-content: center; margin-top: 1.5rem; }
        .pagination a, .pagination span { padding: 0.5rem 0.8rem; margin: 0 0.2rem; background: var(--sidebar-color); border: 1px solid var(--border-color); border-radius: 4px; color: var(--light-text-color); text-decoration: none; }
        .pagination a:hover { background: var(--glass-background); color: var(--text-color); }
        .pagination span.current { background: var(--primary-color); color: #fff; font-weight: 600; border-color: var(--primary-color); }
        .pagination span.disabled { color: #555; background: #222; cursor: not-allowed; }


        /* --- Mensagens --- */
        .message { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; font-weight: 600; border: 1px solid transparent; backdrop-filter: blur(3px); }
        .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: rgba(21, 87, 36, 0.5); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: rgba(114, 28, 36, 0.5); }
        .message.info { background-color: var(--info-bg); color: var(--info-text); border-color: rgba(8, 66, 152, 0.5); }

        /* --- Mobile / Responsivo --- */
        .menu-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1003; cursor: pointer; padding: 8px; background-color: var(--sidebar-color); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow);}
        .menu-toggle svg { width: 20px; height: 20px; color: var(--text-color); display: block; }
        @media (max-width: 1024px) {
            body { position: relative; }
            .sidebar { width: 280px; transform: translateX(-280px); box-shadow: 5px 0 15px rgba(0, 0, 0, 0.5); z-index: 1002; height: 100%; overflow-y: auto; }
            .menu-toggle { display: flex; }
            .main-content { margin-left: 0; width: 100%; padding: 1.5rem; padding-top: 5rem; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            body.sidebar-open::after { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1001; backdrop-filter: blur(2px);}
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .tab-navigation { gap: 0.2rem; padding-left: 0.5rem; }
            .tab-button { padding: 0.7rem 1rem; font-size: 0.85em; }
            /* Tabelas Mobile */
            .list-container table { border: none; min-width: auto; display: block; }
            .list-container thead { display: none; }
            .list-container tr { display: block; margin-bottom: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1rem; background: rgba(0,0,0,0.1); }
            .list-container td { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: none; text-align: right; }
            .list-container td::before { content: attr(data-label); font-weight: 600; color: var(--light-text-color); text-align: left; margin-right: 1rem; flex-basis: 40%;}
            .list-container td.actions { justify-content: flex-end; }
            .list-container td.actions::before { display: none; }
            .avaliacao-comentario { max-width: 100%; }
            .avaliacao-foto:hover { transform: scale(2.5); }
        }
        @media (max-width: 576px) {
            .main-content { padding: 1rem; padding-top: 4.5rem; }
            .content-header { padding: 1rem 1.5rem;}
            .content-header h1 { font-size: 1.5rem; }
            .content-header p { font-size: 0.9rem;}
            .form-container, .list-container { padding: 1rem 1.5rem;}
            .crud-section h3 { font-size: 1.1rem;}
            .form-container h4 { font-size: 1rem;}
            .tab-navigation { flex-wrap: wrap; }
            .tab-button { width: 100%; border-radius: var(--border-radius); margin-bottom: 0.5rem; }
            .tab-button.active { border-radius: var(--border-radius); }
            .tab-pane .crud-section:first-child .form-container,
            .tab-pane .crud-section:first-child .list-container {
                 border-top-left-radius: var(--border-radius);
                 border-top-right-radius: var(--border-radius);
            }
            .list-container td, .list-container td::before { font-size: 0.8em; }
        }

    </style>
</head>
<body>

    <div class="menu-toggle" id="menu-toggle">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </div>

    <div id="particles-js"></div>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>Moderar Avaliações de Produtos</h1>
            <p>Aprove, reprove, edite ou adicione avaliações manualmente.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <div class="tab-navigation">
            <button class="tab-button" data-tab-target="#tab-moderacao">Moderação Pendente (<?php echo count($avaliacoes_pendentes); ?>)</button>
            <button class="tab-button" data-tab-target="#tab-adicionar">Adicionar / Editar Avaliação</button>
            <button class="tab-button" data-tab-target="#tab-aprovadas">Histórico (Aprovadas)</button>
        </div>

        <div class="tab-content-wrapper">

            <div class="tab-pane" id="tab-moderacao">
                <div class="crud-section" id="section-pendentes">
                    <h3>Avaliações Pendentes de Moderação</h3>
                    <div class="list-container">
                        <?php if (empty($avaliacoes_pendentes)): ?>
                            <p style="text-align: center; color: var(--light-text-color); padding: 1rem 0;">Nenhuma avaliação pendente no momento.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Produto</th>
                                        <th>Avaliador</th>
                                        <th>Rating</th>
                                        <th>Comentário</th>
                                        <th>Foto</th>
                                        <th class="actions">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($avaliacoes_pendentes as $av): ?>
                                        <tr>
                                            <td data-label="Data"><?php echo date('d/m/Y H:i', strtotime($av['data_avaliacao'])); ?></td>
                                            <td data-label="Produto"><?php echo htmlspecialchars($av['produto_nome']); ?></td>
                                            <td data-label="Avaliador"><?php echo htmlspecialchars($av['nome_avaliador']); ?> <br><small>(<?php echo htmlspecialchars($av['usuario_nome'] ?? 'Visitante'); ?>)</small></td>
                                            <td data-label="Rating"><span class="avaliacao-rating">★ <?php echo $av['classificacao']; ?></span></td>
                                            <td data-label="Comentário"><div class="avaliacao-comentario"><?php echo nl2br(htmlspecialchars($av['comentario'])); ?></div></td>
                                            <td data-label="Foto">
                                                <?php if (!empty($av['foto_url'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($av['foto_url']); ?>" alt="Foto" class="avaliacao-foto">
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <a href="avaliacoes.php?toggle_aprovado=<?php echo $av['id']; ?>&status=1" class="approve">Aprovar</a>
                                                <a href="avaliacoes.php?edit_avaliacao=<?php echo $av['id']; ?>#tab-adicionar">Editar</a>
                                                <a href="avaliacoes.php?delete_avaliacao=<?php echo $av['id']; ?>" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane" id="tab-adicionar">
                <div class="crud-section" id="form-avaliacao-manual">
                    <h3><?php echo $is_editing_avaliacao ? 'Editar Avaliação' : 'Adicionar Avaliação Manual'; ?></h3>
                    <div class="form-container">
                        <form action="avaliacoes.php#form-avaliacao-manual" method="POST" enctype="multipart/form-data">
                            <?php if ($is_editing_avaliacao): ?>
                                <input type="hidden" name="avaliacao_id" value="<?php echo $edit_avaliacao['id']; ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="produto_id">Produto Avaliado:</label>
                                <select id="produto_id" name="produto_id" required>
                                    <option value="">-- Selecione um Produto --</option>
                                    <?php foreach($all_produtos as $produto): ?>
                                        <option value="<?php echo $produto['id']; ?>"
                                            <?php echo ($is_editing_avaliacao && $edit_avaliacao['produto_id'] == $produto['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($produto['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="nome_avaliador">Nome do Avaliador:</label>
                                    <input type="text" id="nome_avaliador" name="nome_avaliador" value="<?php echo htmlspecialchars($edit_avaliacao['nome_avaliador'] ?? 'Admin'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="classificacao">Classificação (Estrelas):</label>
                                    <select id="classificacao" name="classificacao" required>
                                        <option value="5" <?php echo (($edit_avaliacao['classificacao'] ?? 5) == 5) ? 'selected' : ''; ?>>5 Estrelas</option>
                                        <option value="4" <?php echo (($edit_avaliacao['classificacao'] ?? 5) == 4) ? 'selected' : ''; ?>>4 Estrelas</option>
                                        <option value="3" <?php echo (($edit_avaliacao['classificacao'] ?? 5) == 3) ? 'selected' : ''; ?>>3 Estrelas</option>
                                        <option value="2" <?php echo (($edit_avaliacao['classificacao'] ?? 5) == 2) ? 'selected' : ''; ?>>2 Estrelas</option>
                                        <option value="1" <?php echo (($edit_avaliacao['classificacao'] ?? 5) == 1) ? 'selected' : ''; ?>>1 Estrela</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="comentario">Comentário:</label>
                                <textarea id="comentario" name="comentario"><?php echo htmlspecialchars($edit_avaliacao['comentario'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="foto_arquivo">Foto (Upload Opcional):</label>
                                <input type="file" id="foto_arquivo" name="foto_arquivo">

                                <input type="hidden" name="foto_url_existente" value="<?php echo htmlspecialchars($edit_avaliacao['foto_url'] ?? ''); ?>">

                                <?php if ($is_editing_avaliacao && !empty($edit_avaliacao['foto_url'])): ?>
                                    <p style="margin-top: 10px; color: var(--light-text-color);">
                                        Foto atual: <br>
                                        <img src="../<?php echo htmlspecialchars($edit_avaliacao['foto_url']); ?>" alt="Preview" style="max-width: 100px; height: auto; margin-top: 5px; border-radius: 4px; background: #fff; padding: 3px;">
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="form-group-check">
                                <input type="checkbox" id="aprovado" name="aprovado" value="1"
                                    <?php
                                        // Se estiver editando, usa o valor do DB. Se for novo, marca por padrão.
                                        echo ($is_editing_avaliacao && !empty($edit_avaliacao['aprovado'])) || !$is_editing_avaliacao ? 'checked' : '';
                                    ?>>
                                <label for="aprovado">Aprovar esta avaliação (Exibir no site)</label>
                            </div>

                            <button type="submit" name="salvar_avaliacao" class="<?php echo $is_editing_avaliacao ? 'update' : ''; ?>" style="margin-top: 1rem;">
                                <?php echo $is_editing_avaliacao ? 'Salvar Alterações' : 'Adicionar Avaliação'; ?>
                            </button>
                            <?php if ($is_editing_avaliacao): ?>
                                <a href="avaliacoes.php" class="cancel">Cancelar Edição</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane" id="tab-aprovadas">
                <div class="crud-section" id="section-aprovadas">
                    <h3>Histórico de Avaliações Aprovadas</h3>
                    <div class="list-container">
                         <?php if (empty($avaliacoes_aprovadas)): ?>
                            <p style="text-align: center; color: var(--light-text-color); padding: 1rem 0;">Nenhuma avaliação aprovada encontrada.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Produto</th>
                                        <th>Avaliador</th>
                                        <th>Rating</th>
                                        <th>Comentário</th>
                                        <th>Foto</th>
                                        <th class="actions">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($avaliacoes_aprovadas as $av): ?>
                                        <tr>
                                            <td data-label="Data"><?php echo date('d/m/Y H:i', strtotime($av['data_avaliacao'])); ?></td>
                                            <td data-label="Produto"><?php echo htmlspecialchars($av['produto_nome']); ?></td>
                                            <td data-label="Avaliador"><?php echo htmlspecialchars($av['nome_avaliador']); ?> <br><small>(<?php echo htmlspecialchars($av['usuario_nome'] ?? 'Visitante'); ?>)</small></td>
                                            <td data-label="Rating"><span class="avaliacao-rating">★ <?php echo $av['classificacao']; ?></span></td>
                                            <td data-label="Comentário"><div class="avaliacao-comentario"><?php echo nl2br(htmlspecialchars($av['comentario'])); ?></div></td>
                                            <td data-label="Foto">
                                                <?php if (!empty($av['foto_url'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($av['foto_url']); ?>" alt="Foto" class="avaliacao-foto">
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <a href="avaliacoes.php?toggle_aprovado=<?php echo $av['id']; ?>&status=0" class="reprove">Reprovar</a>
                                                <a href="avaliacoes.php?edit_avaliacao=<?php echo $av['id']; ?>#tab-adicionar">Editar</a>
                                                <a href="avaliacoes.php?delete_avaliacao=<?php echo $av['id']; ?>" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="avaliacoes.php?page=<?php echo $page - 1; ?>#tab-aprovadas">&laquo; Anterior</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; Anterior</span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="avaliacoes.php?page=<?php echo $i; ?>#tab-aprovadas" class="<?php echo ($i == $page) ? 'current' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="avaliacoes.php?page=<?php echo $page + 1; ?>#tab-aprovadas">Próxima &raquo;</a>
                        <?php else: ?>
                            <span class="disabled">Próxima &raquo;</span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <script>
        // --- JavaScript para Partículas ---
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- JavaScript Específico da Página ---
        document.addEventListener('DOMContentLoaded', () => {
            // A Lógica de Menu e Perfil foi removida, pois está no admin_sidebar.php

            // --- Lógica das ABAS (TABS) ---
            const tabButtons = document.querySelectorAll('.tab-navigation .tab-button');
            const tabPanes = document.querySelectorAll('.tab-content-wrapper .tab-pane');

            tabButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanes.forEach(pane => pane.classList.remove('active'));

                    button.classList.add('active');
                    const targetPaneId = button.getAttribute('data-tab-target');
                    const targetPane = document.querySelector(targetPaneId);
                    if (targetPane) {
                        targetPane.classList.add('active');
                    }

                    // Limpa a query string (se houver) e define o hash da aba
                    const newUrl = window.location.pathname + targetPaneId;
                    history.pushState(null, null, newUrl);
                });
            });

            // --- Lógica para Ancoragem e Abas ---
            const urlParams = new URLSearchParams(window.location.search);
            const urlHash = window.location.hash;

            function activateTabFromHash(hash) {
                let targetPane = null;
                let elementToScroll = null;

                if (hash.startsWith('#tab-')) {
                    targetPane = document.querySelector(hash);
                    elementToScroll = targetPane;
                } else if (hash) {
                    const targetElement = document.querySelector(hash);
                    if (targetElement) {
                        targetPane = targetElement.closest('.tab-pane');
                        elementToScroll = targetElement;
                    }
                }
                
                // Se estiver editando, força a aba de adicionar/editar
                if (urlParams.has('edit_avaliacao')) {
                    const editHash = '#tab-adicionar';
                    targetPane = document.querySelector(editHash);
                    elementToScroll = document.querySelector('#form-avaliacao-manual'); // Define o scroll para o form
                }


                if (targetPane) {
                    const targetTabButton = document.querySelector(`.tab-button[data-tab-target="#${targetPane.id}"]`);
                    if (targetTabButton && !targetTabButton.classList.contains('active')) {
                        tabButtons.forEach(btn => btn.classList.remove('active'));
                        tabPanes.forEach(pane => pane.classList.remove('active'));
                        targetTabButton.classList.add('active');
                        targetPane.classList.add('active');
                    }
                } else {
                    // Padrão: Ativa a primeira aba (Moderação / Lista)
                    const firstTabButton = document.querySelector('.tab-navigation .tab-button:first-child');
                    const firstPane = document.querySelector('.tab-content-wrapper .tab-pane:first-child');
                    if (firstTabButton && firstPane) {
                        firstTabButton.classList.add('active');
                        firstPane.classList.add('active');
                    }
                }

                // Rola para a seção específica
                if (elementToScroll) {
                    setTimeout(() => {
                        const headerElement = elementToScroll.querySelector('h3') || elementToScroll.querySelector('h4') || elementToScroll;
                        if(headerElement) {
                           headerElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }, 150);
                }
            }

            // Ativa a aba correta no carregamento da página
            activateTabFromHash(urlHash);

        });
    </script>
</body>
</html>
