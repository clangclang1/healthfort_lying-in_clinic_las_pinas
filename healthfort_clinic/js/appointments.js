
(function () {
    var app = document.getElementById("app");
    var button = document.getElementById("menu-btn");
    var closeButton = document.getElementById("sidebar-close");
    var welcome = document.querySelector(".welcome");
    var desktop = window.matchMedia("(min-width: 961px)");

    function isOpen() {
        return desktop.matches
            ? !app.classList.contains("nav-collapsed")
            : app.classList.contains("nav-open");
    }

    function sync() {
        button.setAttribute("aria-expanded", isOpen() ? "true" : "false");
    }

    function openMenu() {
        if (desktop.matches) {
            app.classList.remove("nav-collapsed");
        } else {
            app.classList.add("nav-open");
        }
        sync();
    }

    function closeMenu() {
        if (desktop.matches) {
            app.classList.add("nav-collapsed");
        } else {
            app.classList.remove("nav-open");
        }
        sync();
    }

    button.addEventListener("click", openMenu);
    closeButton.addEventListener("click", closeMenu);

    desktop.addEventListener("change", function () {
        app.classList.remove("nav-open");
        sync();
    });

    function float() {
        button.classList.toggle("is-floating", welcome.getBoundingClientRect().bottom < 48);
    }

    window.addEventListener("scroll", float, { passive: true });
    window.addEventListener("resize", float);

    sync();
    float();
})();



(function () {
    var clock = document.getElementById("clock");
    if (!clock) return;

    var dateFormat = new Intl.DateTimeFormat("en-US", {
        weekday: "short", month: "short", day: "numeric", year: "numeric"
    });
    var timeFormat = new Intl.DateTimeFormat("en-US", {
        hour: "numeric", minute: "2-digit", second: "2-digit", hour12: true
    });

    function tick() {
        var now = new Date();
        clock.textContent = dateFormat.format(now) + " · " + timeFormat.format(now);
        clock.setAttribute("datetime", now.toISOString());
    }

    tick();
    setInterval(tick, 1000);
})();



(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll("#rows tr"));
    var search = document.getElementById("search");
    var fStatus = document.getElementById("filter-status");
    var fService = document.getElementById("filter-service");
    var fDate = document.getElementById("filter-date");
    var clear = document.getElementById("clear-filters");
    var count = document.getElementById("count");
    var tbody = document.getElementById("rows");

    var PILL = { Confirmed: "pill-ok", Waiting: "pill-wait", Completed: "pill-done", Cancelled: "pill-cancel" };
    var NEXT = { confirm: "Confirmed", complete: "Completed", cancel: "Cancelled" };
    var ACTIONS = {
        Waiting: [["confirm", "Confirm"], ["cancel", "Cancel"]],
        Confirmed: [["complete", "Complete"], ["cancel", "Cancel"]],
        Completed: [],
        Cancelled: [["confirm", "Restore"]]
    };

    function renderRow(tr) {
        var status = tr.getAttribute("data-status");
        var pill = tr.querySelector(".pill");
        pill.textContent = status;
        pill.className = "pill " + PILL[status];

        var cell = tr.querySelector(".actions");
        cell.innerHTML = "";
        var actions = ACTIONS[status];
        if (!actions.length) {
            cell.textContent = "\u2014";
            return;
        }
        actions.forEach(function (a) {
            var b = document.createElement("button");
            b.type = "button";
            b.className = "act act-" + a[0];
            b.setAttribute("data-action", a[0]);
            b.textContent = a[1];
            cell.appendChild(b);
        });
    }

    function apply() {
        var q = search.value.trim().toLowerCase();
        var s = fStatus.value;
        var sv = fService.value;
        var d = fDate.value;
        var shown = 0;
        var totals = { Confirmed: 0, Waiting: 0, Cancelled: 0 };

        rows.forEach(function (tr) {
            var status = tr.getAttribute("data-status");
            if (totals[status] !== undefined) totals[status]++;
            var ok = (!q || tr.getAttribute("data-name").toLowerCase().indexOf(q) !== -1)
                && (!s || status === s)
                && (!sv || tr.getAttribute("data-service") === sv)
                && (!d || tr.getAttribute("data-date") === d);
            tr.hidden = !ok;
            if (ok) shown++;
        });

        count.textContent = "Showing " + shown + " of " + rows.length;
        document.getElementById("stat-total").textContent = rows.length;
        document.getElementById("stat-confirmed").textContent = totals.Confirmed;
        document.getElementById("stat-waiting").textContent = totals.Waiting;
        document.getElementById("stat-cancelled").textContent = totals.Cancelled;
    }

    tbody.addEventListener("click", function (e) {
        var btn = e.target.closest("button[data-action]");
        if (!btn) return;
        var tr = btn.closest("tr");
        tr.setAttribute("data-status", NEXT[btn.getAttribute("data-action")]);
        renderRow(tr);
        apply();
    });

    search.addEventListener("input", apply);
    fStatus.addEventListener("change", apply);
    fService.addEventListener("change", apply);
    fDate.addEventListener("change", apply);

    clear.addEventListener("click", function () {
        search.value = "";
        fStatus.value = "";
        fService.value = "";
        fDate.value = "";
        apply();
    });

    rows.forEach(renderRow);
    apply();
})();



(function () {
    var DOCTORS = [
        { name: "Dr. Ana Villareal", role: "OB-GYN", color: "#c2566b", days: [1, 3, 5], leave: ["2026-11-25"] },
        { name: "Dr. Mark Reyes", role: "Pediatrician", color: "#1c4167", days: [2, 4, 6], leave: [] },
        { name: "Rosa Lim", role: "Midwife", color: "#4f8f87", days: [1, 2, 3, 4, 5, 6], leave: ["2026-11-18"] },
        { name: "Carla Dizon", role: "Midwife", color: "#d89b2b", days: [1, 3, 5, 6], leave: [] }
    ];
    var MONTHS = ["January", "February", "March", "April", "May", "June", "July",
        "August", "September", "October", "November", "December"];
    var STATUS_PILL = { "On duty": "pill-ok", "On leave": "pill-cancel", "Off": "pill-done" };

    var grid = document.getElementById("cal-grid");
    var title = document.getElementById("cal-title");
    var legend = document.getElementById("cal-legend");
    var dateLabel = document.getElementById("status-date");
    var list = document.getElementById("doc-status");

    var today = new Date();
    today.setHours(0, 0, 0, 0);
    var view = new Date(today.getFullYear(), today.getMonth(), 1);
    var selected = new Date(today);

    function pad(n) { return String(n).padStart(2, "0"); }
    function iso(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }
    function sameDay(a, b) { return iso(a) === iso(b); }

    function statusFor(doc, date) {
        if (doc.leave.indexOf(iso(date)) !== -1) return "On leave";
        return doc.days.indexOf(date.getDay()) !== -1 ? "On duty" : "Off";
    }

    function initials(name) {
        return name.replace(/^Dr\.\s*/, "").split(" ").map(function (w) { return w.charAt(0); }).join("").slice(0, 2);
    }

    function renderLegend() {
        legend.innerHTML = "";
        DOCTORS.forEach(function (doc) {
            var item = document.createElement("span");
            var swatch = document.createElement("i");
            swatch.style.background = doc.color;
            item.appendChild(swatch);
            item.appendChild(document.createTextNode(doc.name));
            legend.appendChild(item);
        });
    }

    function renderCalendar() {
        title.textContent = MONTHS[view.getMonth()] + " " + view.getFullYear();
        grid.innerHTML = "";
        var first = new Date(view.getFullYear(), view.getMonth(), 1);
        var start = new Date(first);
        start.setDate(first.getDate() - first.getDay());

        for (var i = 0; i < 42; i++) {
            var d = new Date(start);
            d.setDate(start.getDate() + i);

            var btn = document.createElement("button");
            btn.type = "button";
            btn.className = "cal-day";
            btn.setAttribute("data-date", iso(d));
            if (d.getMonth() !== view.getMonth()) btn.classList.add("is-outside");
            if (sameDay(d, today)) btn.classList.add("is-today");
            if (sameDay(d, selected)) btn.classList.add("is-selected");

            var num = document.createElement("span");
            num.className = "cal-num";
            num.textContent = d.getDate();
            btn.appendChild(num);

            var dots = document.createElement("span");
            dots.className = "cal-dots";
            DOCTORS.forEach(function (doc) {
                if (statusFor(doc, d) === "On duty") {
                    var dot = document.createElement("span");
                    dot.className = "cal-dot";
                    dot.style.background = doc.color;
                    dot.title = doc.name;
                    dots.appendChild(dot);
                }
            });
            btn.appendChild(dots);
            grid.appendChild(btn);
        }
    }

    function renderStatus() {
        dateLabel.textContent = selected.toLocaleDateString("en-US", {
            weekday: "long", month: "short", day: "numeric", year: "numeric"
        });
        list.innerHTML = "";
        DOCTORS.forEach(function (doc) {
            var st = statusFor(doc, selected);

            var li = document.createElement("li");
            var avatar = document.createElement("span");
            avatar.className = "doc-avatar";
            avatar.style.background = doc.color;
            avatar.textContent = initials(doc.name);

            var info = document.createElement("div");
            info.className = "doc-info";
            var name = document.createElement("strong");
            name.textContent = doc.name;
            var role = document.createElement("span");
            role.textContent = doc.role;
            info.appendChild(name);
            info.appendChild(role);

            var pill = document.createElement("span");
            pill.className = "pill " + STATUS_PILL[st];
            pill.textContent = st;

            li.appendChild(avatar);
            li.appendChild(info);
            li.appendChild(pill);
            list.appendChild(li);
        });
    }

    document.getElementById("cal-prev").addEventListener("click", function () {
        view = new Date(view.getFullYear(), view.getMonth() - 1, 1);
        renderCalendar();
    });

    document.getElementById("cal-next").addEventListener("click", function () {
        view = new Date(view.getFullYear(), view.getMonth() + 1, 1);
        renderCalendar();
    });

    grid.addEventListener("click", function (e) {
        var btn = e.target.closest(".cal-day");
        if (!btn) return;
        var parts = btn.getAttribute("data-date").split("-");
        selected = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (selected.getMonth() !== view.getMonth() || selected.getFullYear() !== view.getFullYear()) {
            view = new Date(selected.getFullYear(), selected.getMonth(), 1);
        }
        renderCalendar();
        renderStatus();
    });

    renderLegend();
    renderCalendar();
    renderStatus();
})();