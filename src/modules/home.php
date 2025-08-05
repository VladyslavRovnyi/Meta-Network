<?php

defined('BASE_URL') OR exit('No direct script access allowed');

// If user is logged
if (isset($_SESSION["login"])) {
    // Gets the username
    $user = $_SESSION["user"];
    $userEsc = htmlspecialchars($user, ENT_QUOTES, 'UTF-8');

    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <form class="form-horizontal" name="app" id="app" method="post" action="./index.php?page=post" enctype="multipart/form-data">

                    <!-- Object #text binded with the 'letterCount' function -->
                    <div id="text" class="form-group">
                        <div class="input-group">
                            <!-- bind text entry with Vue.js Attribute 'quote' -->
                            <textarea v-model="quote" name="quote" id="quote" class="form-control custom-control" rows="3" maxlength="120" style="resize:none" placeholder="Quote" autofocus></textarea>
                            <!--If quote.length == 0 then button disabled -->
                            <span v-if="quote.length == 0" class="input-group-addon btn btn-primary" onclick="return false;">Send</span>
                            <!--If quote.length != 0 then button enabled -->
                            <span v-if="quote.length != 0" class="input-group-addon btn btn-primary" onclick="document.getElementById('app').submit();">Send</span>
                        </div>
                        <!--display quote.length -->
                        <p class="pull-right">{{ quote && quote.length ? quote.length : 0 }}</p>
                    </div>

                    <!-- Image upload (optional) -->
                    <div class="form-group" style="margin-top:8px;">
                        <input type="file" name="photo" accept="image/png,image/jpeg" class="form-control">
                        <p class="help-block">Optional image: JPG/PNG, up to 3 MB.</p>
                    </div>

                    <!-- Object #table binded with the 'loadTable' function -->
                    <div id="table" class="form-group lg">
                        <div class="form-group">
                            <!-- Search text -->
                            <div class="input-group pull-right">
                                <input type="text" v-model="quote" v-if="items.length" name="search" id="search" class="form-control" placeholder="Search">
                            </div>
                        </div>

                        <!-- publications -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>USER</th>
                                    <th>QUOTE</th>
                                    <th>DATE</th>
                                    <th>TIME</th>
                                    <th>LIKES</th>
                                    <th>COMMENTS</th>
                                    <th>VOTE</th>
                                    <th>DELETE</th>
                                </tr>
                                </thead>

                                <?php
                                // Gets the database connection
                                $conn = getConnection();

                                try {
                                    // Gets the publications
                                    $sql = "SELECT  Q.ID_QUOTE AS id,
                                                Q.QUOTE AS quote,
                                                Q.POST_DATE AS postdate,
                                                Q.POST_TIME AS posttime,
                                                Q.LIKES AS likes,
                                                Q.IMAGE AS image,
                                                U.GUID AS guid,
                                                U.USERNAME AS user
                                        FROM    QUOTES AS Q
                                        INNER JOIN USERS AS U 
                                                ON Q.ID_USER = U.ID_USER
                                        ORDER BY likes DESC;";
                                    $stmt = $conn->prepare($sql);
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

                                            // who liked this post
                                            $sql = "SELECT U.GUID AS guid, U.USERNAME AS user
                                                FROM   LIKES AS L
                                                INNER JOIN USERS AS U ON L.ID_USER = U.ID_USER
                                                WHERE  L.ID_QUOTE = :id_quote;";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->bindParam(':id_quote', $value['id']);
                                            $stmt->execute();
                                            $obj->users = $stmt->fetchAll();

                                            // comments for the post
                                            $sql = "SELECT C.ID_COMMENT AS id,
                                                       C.BODY       AS body,
                                                       C.CREATED_AT AS created,
                                                       U.GUID       AS guid,
                                                       U.USERNAME   AS user
                                                FROM COMMENTS C
                                                JOIN USERS U ON C.ID_USER = U.ID_USER
                                                WHERE C.ID_QUOTE = :id_quote
                                                ORDER BY C.ID_COMMENT ASC";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->bindParam(':id_quote', $value['id']);
                                            $stmt->execute();
                                            $obj->comments = $stmt->fetchAll();

                                            $list[] = $obj;
                                        }

                                        // Gets publications in a Json Array
                                        $items = json_encode($list);
                                        ?>

                                        <tbody>
                                        <!-- Go through the items that matches with the search -->
                                        <tr v-for="(item, index) in search">
                                            <template v-if="index >= start && index < start + size">
                                                <!-- Show username -->
                                                <td><a v-bind:href="'<?= $baseUrl; ?>/profile/' + item.guid">@{{ item.user }}</a></td>

                                                <!-- QUOTE + optional image -->
                                                <!-- owner -->
                                                <th v-if="item.user == '<?= $userEsc; ?>'">
                                                    {{ item.quote }}
                                                    <div v-if="item.image" style="margin-top:8px;">
                                                        <img v-bind:src="'/uploads/posts/' + item.image"
                                                             class="img-thumbnail"
                                                             style="max-width:320px; max-height:240px;">
                                                    </div>
                                                </th>
                                                <!-- not owner -->
                                                <td v-else>
                                                    {{ item.quote }}
                                                    <div v-if="item.image" style="margin-top:8px;">
                                                        <img v-bind:src="'/uploads/posts/' + item.image"
                                                             class="img-thumbnail"
                                                             style="max-width:320px; max-height:240px;">
                                                    </div>
                                                </td>

                                                <!-- date of post -->
                                                <td>{{ item.postdate }}</td>
                                                <!-- time of post -->
                                                <td>{{ item.posttime }}</td>

                                                <!-- # of likes -->
                                                <td>
                                                    <a data-toggle="modal" v-bind:data-target="'#users-' + item.id" style="cursor: pointer;">{{ item.likes }}</a>
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
                                                <td v-if="!contains(item.id)"><a v-bind:href="'/like?id=' + item.id" class="btn btn-primary"><span class="glyphicon glyphicon-heart"></span></a></td>
                                                <td v-if="contains(item.id)"><a v-bind:href="'/unlike?id=' + item.id" class="btn btn-default"><span class="glyphicon glyphicon-heart"></span></a></td>

                                                <!-- delete (owner only) -->
                                                <td><a v-if="item.user == '<?= $userEsc; ?>'" v-bind:href="'/delete?id=' + item.id" class="btn btn-danger"><span class="glyphicon glyphicon-trash"></span></a></td>
                                            </template>
                                        </tr>
                                        </tbody>

                                        <?php
                                    } else {
                                        echo "<tbody><td class='danger' colspan='8' align='center'>No results were obtained</td></tbody>";
                                    }
                                } catch (PDOException $e) {
                                    $_SESSION['message'] = "<strong>DataBase Error</strong>: No results were obtained.<br>" . $e->getMessage();
                                    header('location: ../../public/home');
                                } catch (Exception $e) {
                                    $_SESSION['message'] = "<strong>General Error</strong>: No results were obtained.<br>" . $e->getMessage();
                                    header('location: ../../public/home');
                                } finally {
                                    $conn = null;
                                }
                                ?>

                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="items.length" class="form-group pull-right">
                            <ul class="pagination">
                                <li v-if="page > 1">
                                    <a href="#" v-on:click="prev"><span aria-hidden="true">&laquo;</span></a>
                                </li>
                                <li v-for="i in count" v-bind:class="{ active: page == i }">
                                    <a href="#" v-on:click="page = i">{{ i }}</a>
                                </li>
                                <li v-if="page < count">
                                    <a href="#" v-on:click="next"><span aria-hidden="true">&raquo;</span></a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php

    // Call the javascript functions in the function file
    // Call Vue.js 'letterCount'
    letterCount();

    // If var items is defined then call Vue.js 'loadTable'
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
    header('location: index.php');
}

?>

<!-- AJAX helper for comments -->
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
