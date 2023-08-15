<div class="container">
    <div class="row">
        <form action="/search" method="get">
            <label>
                <input type="text" name="search">
            </label>
            <input type="submit" title="Search" value="Search">
        </form>
    </div>

    <% if $Results %>
        <div class="row">
            <p>$NumResults found:</p>
            <ul>
                <% loop $Results %>
                    <li><a href="$link">$title</a></li>
                <% end_loop %>
            </ul>
        </div>

        <% if $Results.MoreThanOnePage %>
            <% if $Results.NotFirstPage %>
                <a class="prev" href="$Results.PrevLink">Prev</a>
            <% end_if %>
            <% loop $Results.PaginationSummary %>
                <% if $CurrentBool %>
                    $PageNum
                <% else %>
                    <% if $Link %>
                        <a href="$Link">$PageNum</a>
                    <% else %>
                        ...
                    <% end_if %>
                <% end_if %>
            <% end_loop %>
            <% if $Results.NotLastPage %>
                <a class="next" href="$Results.NextLink">Next</a>
            <% end_if %>
        <% end_if %>
    <% end_if %>

</div>
