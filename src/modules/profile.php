<?php

defined('BASE_URL') OR exit('No direct script access allowed');

// If user is logged
if (isset($_SESSION["login"])) {

    // GUID from URL
    $guid = $_GET["guid"] ?? '';

    // Load user info
    $conn = getConnection();

    try {
        $sql = "SELECT  U.ID_USER     AS id,
                        U.USERNAME    AS user,
                        U.AVATAR      AS avatar,
                        U.CREATED_AT  AS created,
                        C.COUNTRY     AS country
                FROM    USERS AS U
                INNER JOIN COUNTRIES AS C
                        ON U.ID_COUNTRY = C.ID_COUNTRY
                WHERE   U.GUID = :guid;";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":guid", $guid);
        $stmt->execute();
        $query = $stmt->fetchObject();

        if ($query) {
            $id      = (int)$query->id;
            $user    = $query->user;
            $created = date_create($query->created);
            $country = $query->country;

            $avatarUrl = !empty($query->avatar)
                ? '/uploads/avatars/' . htmlspecialchars($query->avatar, ENT_QUOTES, 'UTF-8')
                : '/assets/default-avatar.png';
        } else {
            $_SESSION["message"] = "The user specified does not exist.";
            header('location: index.php'); exit;
        }
    } catch (PDOException $e) {
        $_SESSION["message"] = "<strong>DataBase Error</strong>: The user could not be found.<br>" . $e->getMessage();
        header('location: index.php'); exit;
    } catch (Exception $e) {
        $_SESSION["message"] = "<strong>General Error</strong>: The user could not be found.<br>" . $e->getMessage();
        header('location: index.php'); exit;
    } finally {
        $conn = null;
    }

    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">

                <!-- Profile edit dropdown -->
                <div class="btn-group" style="margin-top:12px;">
                    <button type="button"
                            class="btn btn-default dropdown-toggle"
                            data-toggle="dropdown"
                            aria-haspopup="true"
                            aria-expanded="false">
                        <span class="glyphicon glyphicon-cog"></span> Edit profile <span class="caret"></span>
                    </button>

                    <ul class="dropdown-menu" style="padding:15px; min-width:260px;">
                        <form action="/index.php?page=profile_update"
                              method="post"
                              enctype="multipart/form-data"
                              style="width:230px;">

                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <!-- Username -->
                            <div class="form-group">
                                <label for="username" class="control-label">Username</label>
                                <input type="text"
                                       name="username"
                                       id="username"
                                       value="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8'); ?>"
                                       class="form-control input-sm"
                                       maxlength="24" required>
                            </div>

                            <!-- Avatar -->
                            <div class="form-group">
                                <label for="avatar" class="control-label">Avatar (JPG/PNG, ≤1 MB)</label>
                                <input type="file"
                                       name="avatar"
                                       id="avatar"
                                       accept="image/png,image/jpeg"
                                       class="form-control input-sm">
                            </div>

                            <button type="submit" class="btn btn-primary btn-block btn-sm">
                                <span class="glyphicon glyphicon-floppy-disk"></span> Save changes
                            </button>
                        </form>
                    </ul>
                </div>

                <form class="form-horizontal" name="app" id="app">

                    <div class="form-group lg">
                        <img src="<?= $avatarUrl ?>" class="img-circle" width="128" height="128" alt="@<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8'); ?>">

                        <h2 style="margin-top:12px;">@<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <span style="color: gray;">
                            <span class="glyphicon glyphicon-map-marker"></span> <?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?>
                        </span><br>
                        <span style="color: gray;">Joined <?= date_format($created, "F j, Y"); ?></span>
                    </div>

                    <!-- publications -->
                    <div id="table" class="form-group lg">
                        <div class="form-group lg">
                            <div class="input-group pull-right">
                                <input type="text" v-model="quote" v-if="items.length" name="search" id="search" class="form-control" placeholder="Search">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>QUOTE</th>
                                    <th>DATE</th>
                                    <th>TIME</th>
                                    <th>LIKES</th>
                                    <th>COMMENTS</th>
                                    <th>VOTE</th>
                                </tr>
                                </thead>

                                <?php
                                // Re-open connection for posts
                                $conn = getConnection();

                                try {
                                    // All posts by this user
                                    $sql = "SELECT  Q.ID_QUOTE  AS id,
                                                    Q.QUOTE     AS quote,
                                                    Q.POST_DATE AS postdate,
                                                    Q.POST_TIME AS posttime,
                                                    Q.LIKES     AS likes,
                                                    Q.IMAGE     AS image,
                                                    U.GUID      AS guid,
                                                    U.USERNAME  AS user
                                            FROM    QUOTES AS Q
                                            INNER JOIN USERS AS U
                                                    ON Q.ID_USER = U.ID_USER
                                            WHERE   Q.ID_USER = :id
                                            ORDER BY likes DESC";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                                    $stmt->execute();
                                    $query = $stmt->fetchAll();

                                    if ($query) {
                                        $list = [];
                                        foreach ($query as $value) {
                                            $obj = new stdClass();
                                            $obj->id       = $value['id'];
                                            $obj->quote    = $value['quote'];
                                            $obj->postdate = $value['postdate'];
                                            $obj->posttime = $value['posttime'];
                                            $obj->likes    = $value['likes'];
                                            $obj->image    = $value['image'];
                                            $obj->guid     = $value['guid'];
                                            $obj->user     = $value['user'];

                                            // Who liked this post
                                            $sql = "SELECT U.GUID AS guid, U.USERNAME AS user
                                                    FROM   LIKES AS L
                                                    INNER JOIN USERS AS U ON L.ID_USER = U.ID_USER
                                                    WHERE  L.ID_QUOTE = :id_quote;";
                                            $stmt2 = $conn->prepare($sql);
                                            $stmt2->bindParam(':id_quote', $value['id']);
                                            $stmt2->execute();
                                            $obj->users = $stmt2->fetchAll();

                                            // Comments for this post
                                            $sql = "SELECT C.ID_COMMENT AS id,
                                                           C.BODY       AS body,
                                                           C.CREATED_AT AS created,
                                                           U.GUID       AS guid,
                                                           U.USERNAME   AS user
                                                    FROM COMMENTS C
                                                    JOIN USERS U ON C.ID_USER = U.ID_USER
                                                    WHERE C.ID_QUOTE = :id_quote
                                                    ORDER BY C.ID_COMMENT ASC";
                                            $stmt3 = $conn->prepare($sql);
                                            $stmt3->bindParam(':id_quote', $value['id']);
                                            $stmt3->execute();
                                            $obj->comments = $stmt3->fetchAll();

                                            $list[] = $obj;
                                        }

                                        $items = json_encode($list);
                                        ?>

                                        <tbody>
                                        <!-- Iterate items matching the search -->
                                        <tr v-for="item in search">
                                            <!-- Quote + optional image -->
                                            <td>
                                                {{ item.quote }}
                                                <div v-if="item.image" style="margin-top:8px;">
                                                    <img v-bind:src="'/uploads/posts/' + item.image"
                                                         class="img-thumbnail"
                                                         style="max-width:320px; max-height:240px;">
                                                </div>
                                            </td>

                                            <!-- date -->
                                            <td>{{ item.postdate }}</td>
                                            <!-- time -->
                                            <td>{{ item.posttime }}</td>

                                            <!-- likes -->
                                            <td>
                                                <a data-toggle="modal"
                                                   v-bind:data-target="'#users-' + item.id"
                                                   style="cursor: pointer;">
                                                    {{ item.likes }}
                                                </a>
                                                <div class="modal fade" v-bind:id="'users-' + item.id" role="dialog">
                                                    <div class="modal-dialog modal-sm">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                                <h4 class="modal-title">Likes</h4>
                                                            </div>
                                                            <div class="modal-body" style="padding: 0;">
                                                                <ul class="list-group">
                                                                    <li class="list-group-item" v-for="user in item.users">
                                                                        <a v-bind:href="'<?= $baseUrl; ?>/profile/' + user.guid">@{{ user.user }}</a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- comments -->
                                            <td>
                                                <a data-toggle="modal"
                                                   v-bind:data-target="'#comments-' + item.id"
                                                   style="cursor:pointer;">
                                                    {{ item.comments.length }}
                                                </a>

                                                <!-- Modal with comments list and input -->
                                                <div class="modal fade" v-bind:id="'comments-' + item.id" role="dialog">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">

                                                            <div class="modal-header">
                                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                                <h4 class="modal-title">Comments</h4>
                                                            </div>

                                                            <div class="modal-body" style="max-height:380px; overflow-y:auto;">
                                                                <ul class="list-group" v-if="item.comments.length">
                                                                    <li class="list-group-item" v-for="c in item.comments">
                                                                        <a v-bind:href="'<?= $baseUrl; ?>/profile/' + c.guid">@{{ c.user }}</a>:
                                                                        <span>{{ c.body }}</span>
                                                                        <div class="text-muted" style="font-size:12px;">{{ c.created }}</div>
                                                                    </li>
                                                                </ul>
                                                                <p v-else class="text-muted">No comments yet.</p>
                                                            </div>

                                                            <div class="modal-footer">
                                                                <div class="input-group">
                                                                    <input type="text"
                                                                           v-bind:id="'comment-text-' + item.id"
                                                                           class="form-control"
                                                                           maxlength="300"
                                                                           placeholder="Add a comment…">
                                                                    <span class="input-group-btn">
                                                                      <button type="button" class="btn btn-primary"
                                                                              v-on:click="sendComment(item.id)">Send</button>
                                                                    </span>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- like / unlike -->
                                            <td v-if="!contains(item.id)">
                                                <a v-bind:href="'/like?id=' + item.id" class="btn btn-primary">
                                                    <span class="glyphicon glyphicon-heart"></span>
                                                </a>
                                            </td>
                                            <td v-else>
                                                <a v-bind:href="'/unlike?id=' + item.id" class="btn btn-default">
                                                    <span class="glyphicon glyphicon-heart"></span>
                                                </a>
                                            </td>
                                        </tr>
                                        </tbody>

                                        <?php
                                    } else {
                                        echo "<tbody><td class='danger' colspan='6' align='center'>No results were obtained</td></tbody>";
                                    }
                                } catch (PDOException $e) {
                                    $_SESSION["message"] = "<strong>DataBase Error</strong>: No results were obtained.<br>" . $e->getMessage();
                                    header('location: ../../public/home'); exit;
                                } catch (Exception $e) {
                                    $_SESSION["message"] = "<strong>General Error</strong>: No results were obtained.<br>" . $e->getMessage();
                                    header('location: ../../public/home'); exit;
                                } finally {
                                    $conn = null;
                                }
                                ?>

                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    // Vue helpers
    letterCount();

    if (isset($items)) {
        if (isset($_SESSION["voted"])) {
            $voted = json_encode($_SESSION["voted"]);
            loadTable($items, $voted);
        } else {
            $voted = json_encode([]);
            loadTable($items, $voted);
        }
    } else {
        $items = json_encode([]);
        $voted = json_encode([]);
        loadTable($items, $voted);
    }

} else {
    $_SESSION["message"] = "Please login";
    header('location: index.php'); exit;
}

?>

<!-- AJAX helper for adding comments -->
<script>
    function sendComment(quoteId) {
        var $inp = $('#comment-text-' + quoteId);
        var body = ($inp.val() || '').trim();
        if (!body) return;
        $.post('/index.php?page=comment_add', { quote_id: quoteId, body: body })
            .done(function () { location.reload(); })
            .fail(function () { alert('Failed to add comment.'); });
    }
</script>
