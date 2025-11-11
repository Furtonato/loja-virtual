<?php
// buscar.php - Página de Resultados da Busca
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php'; // Conexão $pdo
require_once 'funcoes.php'; // Funções (para carregar configs de cor)

// Carrega as constantes (NOME_DA_LOJA, Mailgun keys, etc.)
if (isset($pdo)) {
    carregarConfigApi($pdo);
}

// 1. Obter o termo de busca da URL
$termo_busca = trim($_GET['q'] ?? '');
$resultados = [];
$total_resultados = 0;

if (!empty($termo_busca)) {
    try {
        // 2. Preparar a query
        // Usamos ILIKE para busca case-insensitive (Padrão PostgreSQL)
        // Usamos '%' para buscar palavras que contenham o termo
        $sql = "SELECT id, nome, preco, preco_antigo, imagem_url, ativo, estoque
                FROM produtos
                WHERE (nome ILIKE :termo OR descricao ILIKE :termo)
                AND ativo = true
                ORDER BY nome ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['termo' => '%' . $termo_busca . '%']);
        $resultados = $stmt->fetchAll();
        $total_resultados = count($resultados);

    } catch (PDOException $e) {
        $errors['db'] = "Erro ao realizar a busca: " . $e->getMessage();
    }
}
// (Se $termo_busca estiver vazio, $resultados continuará sendo um array vazio)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busca por: <?php echo htmlspecialchars($termo_busca); ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

    <style>
        /* ==========================================================
           CSS DA PÁGINA DE BUSCA (GRADE DE PRODUTOS)
           (Copiado da sua index.php para manter a consistência)
           ========================================================== */

        .search-header {
            font-size: 1.8em;
            font-weight: bold;
            color: var(--text-color-dark);
            margin: 15px 0 25px 0;
            text-align: center;
        }
        .search-header span {
            color: var(--green-accent);
        }
        .no-results {
            text-align: center;
            font-size: 1.2em;
            color: var(--text-color-medium);
            padding: 40px 0;
        }

        /* --- Grid de Produtos --- */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            align-items: stretch;
            margin-top: 20px;
        }
        .product-item {
            text-align: center;
            border: 1px solid var(--border-color-light);
            border-radius: 8px;
            padding: 15px;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            display: flex;
            flex-direction: column;
            background-color: #fff;
        }
        .product-item:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }
        .product-item a {
             text-decoration: none;
             color: inherit;
             display: flex;
             flex-direction: column;
             flex-grow: 1;
        }
        .product-image-container {
            position: relative;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .product-image-container img {
             max-width: 100%;
             height: 180px;
             object-fit: contain;
             display: block;
             margin-left: auto;
             margin-right: auto;
             margin-bottom: 0;
             transition: transform 0.3s ease;
        }
        .product-hover-buttons {
            position: absolute;
            bottom: 0;
            left: 0; /* Corrigido de -5 para 0 */
            width: 100%;
            background: var(--cor-acento-fundo-hover); /* Usa a cor dinâmica */
            backdrop-filter: blur(2px);
            padding: 10px;
            display: flex;
            justify-content: center;
            gap: 10px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(100%);
            transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease;
        }
        .product-item a:hover .product-image-container img {
            transform: scale(1.05);
        }
        .product-item a:hover .product-hover-buttons {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .product-hover-buttons.esgotado {
            background: rgba(255, 255, 255, 0.9);
            justify-content: center;
            align-items: center;
        }
        .btn-esgotado {
             font-size: 1em;
             font-weight: bold;
             color: var(--error-color);
             text-transform: uppercase;
        }
        .btn-hover {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 12px;
            border: none;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease;
            background-color: var(--green-accent);
            color: #fff;
            flex-grow: 1;
            max-width: 48%;
        }
        .btn-hover:hover {
             background-color: var(--cor-acento-hover);
        }
        .btn-hover svg {
            width: 16px;
            height: 16px;
        }
        .product-item h3 {
            font-size: 0.95em;
            color: var(--text-color-dark);
            margin-bottom: 10px;
            height: 2.4em; /* Limita a 2 linhas */
            line-height: 1.2em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            min-height: 2.4em;
        }
        .product-item .price {
            margin-top: auto; /* Empurra o preço para baixo */
            font-size: 1.75rem;
            color: var(--green-accent);
            font-weight: 700;
            line-height: 1;
        }

        /* --- Responsividade da Grade --- */
        @media (max-width: 992px) {
            .product-grid { grid-template-columns: repeat(3, 1fr); }
            .product-image-container img { height: 160px; }
        }
        @media (max-width: 768px) {
            .product-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .product-image-container img { height: 140px; }
        }

        /* --- NOVOS ESTILOS DE/POR --- */

/* Ajusta o contêiner da imagem para o selo */
.product-image-container {
    position: relative;
    /* overflow: hidden; (Já deve existir) */
    /* margin-bottom: 15px; (Já deve existir) */
}

/* O selo "Baixou %" */
.discount-badge {
    position: absolute; /* Flutua sobre a imagem */
    top: 10px;      /* 10px do topo */
    left: 10px;     /* 10px da esquerda */
    background-color: #e74c3c; /* Vermelho (cor da Casas Bahia) */
    color: white;
    padding: 3px 8px;
    font-size: 13px; /* Tamanho da fonte do selo */
    font-weight: bold;
    border-radius: 4px;
    z-index: 10;      /* Fica na frente da imagem */
    line-height: 1;
}

/* Contêiner que segura os dois preços e o frete */
.product-item .price-container {
    margin-top: auto; /* Empurra para baixo (MUITO IMPORTANTE) */
    padding-top: 10px;
    line-height: 1.1; /* Espaçamento entre linhas */
}

/* O preço antigo "De:" (riscado) */
.product-item .preco-antigo {
    font-size: 1rem;  /* Tamanho pequeno */
    color: #7f8c8d; /* Cinza */
    text-decoration: line-through; /* O Riscado! */
    display: block;   /* Fica na linha de cima */
}

/* O preço novo "Por:" (grandão) */
.product-item .preco-atual {
    font-size: 1.75rem; /* Tamanho grande */
    color: var(--green-accent); /* Usa a cor verde do seu site */
    font-weight: 700;
}

/* Contêiner da imagem de frete grátis */
.frete-gratis-container {
    margin-top: 8px;
    height: 20px; /* Altura fixa para alinhar todos os cards */
    text-align: left; /* Alinha a imagem à esquerda */
}

.frete-gratis-container img {
    max-width: 120px; /* Limita a largura da sua imagem */
    height: auto;
    max-height: 20px; /* Garante que ela não passe de 20px de altura */
    object-fit: contain; /* Ajusta a imagem sem distorcer */
}

/* --- Ajustes para Celular (Responsivo) --- */
@media (max-width: 768px) {
    .product-item .preco-atual { 
        font-size: 1.5rem; /* Diminui o preço no celular */
    }
    .product-item .preco-antigo { 
        font-size: 0.9rem; /* Diminui o preço riscado no celular */
    }
    .frete-gratis-container, .frete-gratis-container img {
         height: 18px; /* Diminui um pouco a imagem de frete no celular */
         max-height: 18px;
    }
}

@media (max-width: 480px) {
    .product-item .preco-atual { 
        font-size: 1.3rem; 
    }
}
/* --- FIM NOVOS ESTILOS --- */
    </style>
</head>
<body class="search-page">

    <?php include 'templates/header.php'; ?>

    <main class="main-content container">

        <h1 class="search-header">
            <?php if (!empty($termo_busca)): ?>
                Resultados da busca por: <span>"<?php echo htmlspecialchars($termo_busca); ?>"</span>
                <p style="font-size: 0.6em; color: var(--text-color-medium); margin-top: 5px;"><?php echo $total_resultados; ?> produto(s) encontrado(s)</p>
            <?php else: ?>
                Por favor, digite um termo para buscar
            <?php endif; ?>
        </h1>

        <?php if (!empty($errors['db'])): ?>
            <p class="no-results" style="color: var(--error-color);"><?php echo $errors['db']; ?></p>

        <?php elseif (empty($termo_busca)): ?>
            <?php elseif (empty($resultados)): ?>
            <p class="no-results">Nenhum produto foi encontrado para "<?php echo htmlspecialchars($termo_busca); ?>".</p>

        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($resultados as $produto): ?>
                    <?php $is_disponivel = ($produto['ativo'] && $produto['estoque'] > 0); ?>
                    <div class="product-item">
    <a href="produto_detalhe.php?id=<?php echo $produto['id']; ?>">

        <div class="product-image-container">
            <?php
            // --- Início da Lógica de Preço De/Por ---
            $preco_atual = $produto['preco'];
            $preco_antigo = $produto['preco_antigo'] ?? null;
            $desconto_percentual = 0;

            $tem_desconto = !empty($preco_antigo) && $preco_antigo > $preco_atual && $preco_antigo > 0;

            if ($tem_desconto) {
                $desconto_percentual = round((($preco_antigo - $preco_atual) / $preco_antigo) * 100);
            }
            // --- Fim da Lógica de Preço De/Por ---
            ?>

            <?php if ($tem_desconto) : ?>
                <div class="discount-badge">
                    Baixou <?php echo $desconto_percentual; ?>%
                </div>
            <?php endif; ?>

            <?php if (!empty($produto['imagem_url'])): ?>
                <img src="<?php echo htmlspecialchars($produto['imagem_url']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
            <?php else: ?>
                <img src="uploads/placeholder.png" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
            <?php endif; ?>

            <?php if ($is_disponivel): ?>
                <div class="product-hover-buttons">
                    <button type="button" class="btn-hover btn-spy" data-id="<?php echo $produto['id']; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                        ESPIAR
                    </button>
                    <button type="button" class="btn-hover btn-buy-index" data-id="<?php echo $produto['id']; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                        COMPRAR
                    </button>
                </div>
            <?php else: ?>
                <div class="product-hover-buttons esgotado">
                    <span class="btn-esgotado">Esgotado</span>
                </div>
            <?php endif; ?>
        </div>
        <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>

        <div class="price-container">
            <?php if ($tem_desconto) : ?>
                <span class="preco-antigo">R$ <?php echo number_format($preco_antigo, 2, ',', '.'); ?></span>
            <?php endif; ?>
            <span class="preco-atual">R$ <?php echo number_format($preco_atual, 2, ',', '.'); ?></span>
        </div>

        <div class="frete-gratis-container">
            <img src="assets/img/frete-gratis.jpg" alt="Frete Grátis">
        </div>
    </a>
</div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <?php include 'templates/footer.php'; ?>
    <?php include 'templates/modals.php'; // Assumindo que você tem um arquivo de modais (login, carrinho) ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
    <?php include 'templates/scripts.php'; ?>

</body>
</html>
