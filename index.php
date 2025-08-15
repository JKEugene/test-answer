<?php
$host = 'MySQL-8.4';
$user = 'root';
$password = '';
$dbname = 'test';

// Подключение к базе данных
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die('Ошибка подключения: ' . $conn->connect_error);
}

// Получение комментариев через AJAX
if (isset($_GET['get_comments'], $_GET['post_id'])) {
    header('Content-Type: application/json');
    $post_id = (int) $_GET['post_id'];
    $result = $conn->query("SELECT name, email, body FROM comments WHERE postId = $post_id");
    $comments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode($comments);
    $conn->close();
    exit;
}

// Функция для загрузки и декодирования JSON
function fetch_json($url)
{
    $json = file_get_contents($url);
    return json_decode($json, true);
}

// Загрузка данных
$posts = fetch_json('https://jsonplaceholder.typicode.com/posts');
$comments = fetch_json('https://jsonplaceholder.typicode.com/comments');

// Добавление записей в БД
$added_posts = 0;
foreach ($posts as $post) {
    $id = (int) $post['id'];
    $userId = (int) $post['userId'];
    $title = $conn->real_escape_string($post['title']);
    $body = $conn->real_escape_string($post['body']);

    $check = $conn->query("SELECT id FROM posts WHERE id = $id");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO posts (id, userId, title, body) VALUES ($id, $userId, '$title', '$body')");
        $added_posts++;
    }
}

// Добавление комментариев в БД
$added_comments = 0;
foreach ($comments as $comment) {
    $id = (int) $comment['id'];
    $postId = (int) $comment['postId'];
    $name = $conn->real_escape_string($comment['name']);
    $email = $conn->real_escape_string($comment['email']);
    $body = $conn->real_escape_string($comment['body']);

    $check = $conn->query("SELECT id FROM comments WHERE id = $id");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO comments (id, postId, name, email, body) VALUES ($id, $postId, '$name', '$email', '$body')");
        $added_comments++;
    }
}

// Поиск по комментариям
$search_key = isset($_GET['key']) ? trim($conn->real_escape_string($_GET['key'])) : '';
$posts_to_show = [];
$search_comments_count = [];

if ($search_key && mb_strlen($search_key) >= 3) {
    $result = $conn->query("SELECT postId, COUNT(*) as cnt FROM comments WHERE body LIKE '%$search_key%' GROUP BY postId");
    $post_ids = [];
    while ($row = $result->fetch_assoc()) {
        $post_ids[] = (int) $row['postId'];
        $search_comments_count[$row['postId']] = $row['cnt'];
    }
    if ($post_ids) {
        $ids_str = implode(',', $post_ids);
        $posts_result = $conn->query("SELECT * FROM posts WHERE id IN ($ids_str)");
        while ($row = $posts_result->fetch_assoc()) {
            $posts_to_show[] = $row;
        }
    }
} else {
    $posts_result = $conn->query("SELECT * FROM posts");
    while ($row = $posts_result->fetch_assoc()) {
        $posts_to_show[] = $row;
    }
}

// Получаем количество комментариев для каждого поста
$comments_count = [];
$res = $conn->query("SELECT postId, COUNT(*) as cnt FROM comments GROUP BY postId");
while ($row = $res->fetch_assoc()) {
    $comments_count[$row['postId']] = $row['cnt'];
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Решение</title>
</head>

<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        background-color: #f4f4f4;
    }

    .search {
        margin-bottom: 20px;
    }

    .search input[type="text"] {
        padding: 10px;
        width: 300px;
    }

    .search button {
        padding: 10px 15px;
        background-color: #007bff;
        color: white;
        border: none;
        cursor: pointer;
    }

    .search button:hover {
        background-color: #0056b3;
    }

    .posts {
        display: flex;
        flex-direction: column;
    }

    .posts-item {
        background: white;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .posts-item h2 {
        margin: 0 0 10px;
    }

    .posts-item p {
        margin: 0 0 10px;
    }

    .show-comments-btn {
        padding: 5px 10px;
        background-color: #118488ff;
        color: white;
        border: none;
        cursor: pointer;
    }

    .show-comments-btn:hover {
        background-color: #0b6164ff;
    }

    .modal-bg {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 1000;
    }

    .modal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #eeeeeeff;
        padding: 20px;
        z-index: 1001;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
        border-radius: 8px;
    }

    .modal.active,
    .modal-bg.active {
        display: block;
    }

    .close-btn {
        float: right;
        cursor: pointer;
        font-size: 18px;
        padding: 2px 6px;
        border-radius: 4px;
        background-color: #d17164ff;
    }

    .close-btn:hover {
        background-color: #c55a4dff;
    }

    .comm-item {
        background: white;
        padding: 15px;
        margin-top: 10px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .comm-item p {
        margin-top: 5px;
    }

    .comm-item em {
        color: #616161b2;
    }
</style>

<body>

    <div class="search">
        <form method="get" style="display:inline;">
            <input type="text" name="key" placeholder="Поиск.." value="<?php echo htmlspecialchars($search_key); ?>">
            <button type="submit">Найти</button>
        </form>
        <?php
        $found_comments = 0;
        if ($search_key && mb_strlen($search_key) >= 3) {
            $conn2 = new mysqli($host, $user, $password, $dbname);
            $search_key_sql = trim($conn2->real_escape_string($search_key));
            $result = $conn2->query("SELECT COUNT(*) as cnt FROM comments WHERE body LIKE '%$search_key_sql%'");
            if ($row = $result->fetch_assoc()) {
                $found_comments = $row['cnt'];
            }
            $conn2->close();
        }
        ?>
        <?php if ($search_key && mb_strlen($search_key) >= 3): ?>
            <span style="margin-left:15px; font-weight:bold;">
                Найдено комментариев: <?php echo $found_comments; ?>
            </span>
        <?php elseif ($search_key): ?>
            <span style="margin-left:15px; color:#d17164ff;">
                Введите минимум 3 символа для поиска
            </span>
        <?php endif; ?>
    </div>

    <div class="posts">
        <?php foreach ($posts_to_show as $post):
            $cnt = isset($comments_count[$post['id']]) ? $comments_count[$post['id']] : 0;
            $found_cnt = ($search_key && mb_strlen($search_key) >= 3)
                ? (isset($search_comments_count[$post['id']]) ? $search_comments_count[$post['id']] : 0)
                : null;
            if ($found_cnt !== null) {
                $ending = 'ев,';
                if ($found_cnt % 10 == 1 && $found_cnt % 100 != 11) {
                    $ending = 'й,';
                } elseif ($found_cnt % 10 >= 2 && $found_cnt % 10 <= 4 && ($found_cnt % 100 < 10 || $found_cnt % 100 >= 20)) {
                    $ending = 'я,';
                }
                $containing = ($found_cnt == 1) ? 'содержащий' : 'содержащие';
            }
            ?>
            <div class="posts-item" data-post-id="<?php echo $post['id']; ?>">
                <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($post['body'])); ?></p>
                <button type="button" class="show-comments-btn">
                    Показать комментарии (<?php echo $cnt; ?>)
                </button>
                <?php if ($found_cnt !== null): ?>
                    <span style="margin-left:10px; color:#118488ff;">
                        Найдено: <?php echo $found_cnt; ?>
                        комментари<?php echo $ending; ?>
                        <?php echo $containing; ?> "<?php echo htmlspecialchars($search_key); ?>"
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="modal-bg"></div>
    <div class="modal">
        <span class="close-btn">&times;</span>
        <div class="modal-content"></div>
    </div>


    <script>
        // Обработчик кнопки "Показать комментарии"
        document.querySelectorAll('.show-comments-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const postId = this.closest('.posts-item').getAttribute('data-post-id');
                fetch('?get_comments=1&post_id=' + postId)
                    .then(res => res.json())
                    .then(data => {
                        let html = '<center><h3>Комментарии</h3></center>';
                        const searchWord = "<?php echo addslashes($search_key); ?>";
                        const highlight = (text) => {
                            if (!searchWord) return text;
                            return text.replace(new RegExp(searchWord, 'gi'), match => `<span style="background:yellow;color:#222;">${match}</span>`);
                        };
                        const formatText = (text) => highlight(text).replace(/\r?\n/g, '<br>');
                        if (data.length === 0) {
                            html += '<p>Нет комментариев</p>';
                        } else {
                            data.forEach(c => {
                                html += `<div class="comm-item" style="margin-bottom:10px;">
                                            <b>${formatText(c.name)}</b> <em>${c.email}</em>
                                            <p>${formatText(c.body)}</p>
                                        </div>`;
                            });
                        }
                        document.querySelector('.modal-content').innerHTML = html;
                        document.querySelector('.modal-bg').classList.add('active');
                        document.querySelector('.modal').classList.add('active');
                    });
            });
        });
        document.querySelector('.close-btn').onclick = function () {
            document.querySelector('.modal-bg').classList.remove('active');
            document.querySelector('.modal').classList.remove('active');
        };
        document.querySelector('.modal-bg').onclick = function () {
            document.querySelector('.modal-bg').classList.remove('active');
            document.querySelector('.modal').classList.remove('active');
        };
    </script>

    <script>
        // Вывод результатов в консоль
        console.log('Загружено <?php echo $added_posts; ?> записей.\nЗагружено <?php echo $added_comments; ?> комментариев.');
    </script>
</body>

</html>