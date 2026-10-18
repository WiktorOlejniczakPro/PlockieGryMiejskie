package pl.plock.pgm.ui

import android.Manifest
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.IconButton
import androidx.compose.material3.SmallFloatingActionButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import pl.plock.pgm.data.GameDetail
import pl.plock.pgm.data.SessionState
import pl.plock.pgm.data.Waypoint
import pl.plock.pgm.location.PlayerLocation

/** Powyżej tej dokładności (m) nie zgłaszamy dotarcia – pomiar jest zbyt niepewny. */
private const val MAX_ACCURACY_M = 50f
private const val LOW_ACCURACY_MESSAGE = "Nie możemy jeszcze dokładnie określić Twojej lokalizacji. Poczekaj chwilę i spróbuj ponownie."

/** Rozgrywka: mapa z trasą, pozycja gracza i panel bieżącego punktu. */
@Composable
fun PlayScreen(vm: AppViewModel, snackbar: SnackbarHostState) {
    val game = vm.game
    val session = vm.session
    if (game == null || session == null) {
        LoadingBox()
        return
    }

    // --- GPS -------------------------------------------------------------------
    val tracker = vm.locationTracker
    var permissionAsked by remember { mutableStateOf(false) }
    var hasPermission by remember { mutableStateOf(tracker.hasPermission()) }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { result ->
        hasPermission = result.values.any { it }
        if (hasPermission) tracker.start()
    }
    LaunchedEffect(Unit) {
        if (!hasPermission && !permissionAsked && !vm.demoMode) {
            permissionAsked = true
            permissionLauncher.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
        }
    }
    DisposableEffect(hasPermission, session.isCompleted) {
        if (hasPermission && !session.isCompleted) tracker.start()
        onDispose { tracker.stop() }
    }
    val realLocation by tracker.location.collectAsState()
    val location = vm.effectiveLocation(realLocation)

    // --- bieżący punkt ---------------------------------------------------------
    val target: Waypoint? = game.waypoints.firstOrNull { it.id == (vm.selectedWaypointId ?: session.nextWaypointId) }
    val targetState = target?.let { session.stateOf(it.id) }
    val distance = if (target != null && location != null) location.distanceTo(target.latitude, target.longitude) else null
    val inRange = if (target != null && location != null && distance != null) {
        distance <= target.arrivalRadiusM + minOf(location.accuracyM.toDouble(), 30.0)
    } else {
        false
    }

    val isCurrent = target != null && target.id == session.nextWaypointId
    val accurate = location != null && location.accuracyM <= MAX_ACCURACY_M && location.isFresh()

    // Automatyczne „jestem na miejscu” – tylko dla bieżącego punktu trasy i dopiero,
    // gdy pozycja przez 3 s stabilnie mieści się w promieniu (bez skoków GPS).
    // Ponowna próba najwcześniej po 15 s.
    val lastAutoAttempt = remember { HashMap<Int, Long>() }
    val latestLocation by rememberUpdatedState(location)
    val latestInRange by rememberUpdatedState(inRange && accurate)
    LaunchedEffect(inRange && accurate, isCurrent, target?.id, targetState?.reached) {
        if (!(inRange && accurate) || !isCurrent || target == null || targetState?.reached != false) return@LaunchedEffect
        delay(3_000)
        val loc = latestLocation
        if (!latestInRange || loc == null) return@LaunchedEffect
        val now = System.currentTimeMillis()
        if (now - (lastAutoAttempt[target.id] ?: 0L) < 15_000) return@LaunchedEffect
        if (vm.arrive(target.id, loc, silent = true)) lastAutoAttempt[target.id] = now
    }

    // Ekran nie gaśnie w trakcie gry; krótka wibracja po dotarciu do punktu
    val view = LocalView.current
    DisposableEffect(view) {
        view.keepScreenOn = true
        onDispose { view.keepScreenOn = false }
    }
    val haptics = LocalHapticFeedback.current
    var seenArrivals by remember { mutableIntStateOf(vm.arrivals) }
    LaunchedEffect(vm.arrivals) {
        if (vm.arrivals > seenArrivals) haptics.performHapticFeedback(HapticFeedbackType.LongPress)
        seenArrivals = vm.arrivals
    }
    val farAway = realLocation?.isNearPlock() == false && !vm.demoMode

    var taskOpen by remember { mutableStateOf(false) }
    var listOpen by remember { mutableStateOf(false) }
    var focusKey by remember { mutableIntStateOf(0) }
    var focus by remember { mutableStateOf<MapFocus?>(null) }
    fun focusOn(lat: Double, lng: Double, zoom: Double? = null) {
        focusKey++
        focus = MapFocus(focusKey, lat, lng, zoom)
    }
    // po zmianie celu (następny punkt trasy) mapa przesuwa się na niego,
    // a włączone prowadzenie wyznacza trasę do nowego celu
    LaunchedEffect(target?.id) {
        if (target != null && focusKey > 0) focusOn(target.latitude, target.longitude)
    }
    // Trasa do bieżącego punktu wyznacza się sama, gdy tylko jest pozycja GPS
    // (i po każdym zaliczonym punkcie). Krzyżyk na pasku trasy ją wyłącza.
    val hasLocation = location != null
    LaunchedEffect(target?.id, hasLocation, isCurrent) {
        val loc = latestLocation ?: return@LaunchedEffect
        if (target == null || !isCurrent || session.isCompleted || vm.routeDismissed) return@LaunchedEffect
        val r = vm.navRoute
        if (r == null || r.targetLat != target.latitude || r.targetLng != target.longitude) {
            vm.startRoute(loc, target.latitude, target.longitude, target.name, silent = true)
        }
    }
    LaunchedEffect(session.isCompleted) { if (session.isCompleted) vm.stopRoute() }
    // nowa trasa – widok obejmuje gracza i cel
    LaunchedEffect(vm.routeVersion) {
        val r = vm.navRoute ?: return@LaunchedEffect
        focusKey++
        focus = MapFocus(focusKey, r.targetLat, r.targetLng, fit = r.points)
    }
    // zejście z trasy – nowa trasa
    LaunchedEffect(location) { vm.followRoute(location) }

    Box(Modifier.fillMaxSize()) {
        OsmMap(
            points = game.waypoints.map { wp ->
                MapPoint(
                    id = wp.id,
                    number = wp.position,
                    latitude = wp.latitude,
                    longitude = wp.longitude,
                    state = when {
                        session.stateOf(wp.id)?.completed == true -> PointState.DONE
                        wp.id == target?.id -> PointState.TARGET
                        else -> PointState.TODO
                    },
                )
            },
            player = location,
            targetRadiusM = target?.arrivalRadiusM,
            focus = focus,
            navRoute = vm.navRoute?.points,
            onPointClick = { vm.selectedWaypointId = it },
            onLongPress = if (vm.demoMode) { lat, lng ->
                vm.simulatedLocation = PlayerLocation(lat, lng, 5f, simulated = true)
            } else null,
            modifier = Modifier.fillMaxSize(),
        )

        // Górny pasek: powrót + postęp
        Surface(
            modifier = Modifier.fillMaxWidth().statusBarsPadding().padding(12.dp),
            shape = RoundedCornerShape(16.dp),
            shadowElevation = 4.dp,
        ) {
            Column(Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = { vm.back() }) { PgmIcon(PgmIcons.BACK, "Wyjdź z gry") }
                    Text(game.title, fontWeight = FontWeight.Bold, maxLines = 1, modifier = Modifier.weight(1f))
                    Text("${session.score}/${session.maxScore} pkt", fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                }
                LinearProgressIndicator(
                    progress = { if (session.totalWaypoints == 0) 0f else session.completedWaypoints / session.totalWaypoints.toFloat() },
                    modifier = Modifier.fillMaxWidth().padding(top = 4.dp),
                )
                Text(
                    "Ukończone punkty: ${session.completedWaypoints} z ${session.totalWaypoints}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                if (vm.demoAvailable) Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        if (vm.demoMode) "Tryb demo: przytrzymaj palec na mapie = Twoja pozycja" else "Tryb demo (pozycja z mapy)",
                        style = MaterialTheme.typography.labelSmall,
                        color = if (vm.demoMode) MaterialTheme.colorScheme.tertiary else MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.weight(1f),
                    )
                    Switch(checked = vm.demoMode, onCheckedChange = { vm.toggleDemoMode(it) })
                } else if (location != null) {
                    Text(
                        "GPS: dokładność ±${location.accuracyM.toInt()} m",
                        style = MaterialTheme.typography.labelSmall,
                        color = if (location.accuracyM <= MAX_ACCURACY_M) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.tertiary,
                    )
                }
                RouteBar(vm, location, Modifier.padding(top = 6.dp))
            }
        }

        // Przyciski mapy: moja pozycja, cel, lista punktów
        Column(
            Modifier.align(Alignment.CenterEnd).padding(end = 12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            SmallFloatingActionButton(onClick = {
                if (location != null) focusOn(location.latitude, location.longitude, 17.0)
                else vm.message = if (vm.demoMode) "Przytrzymaj palec na mapie, aby ustawić pozycję." else "Szukam sygnału GPS…"
            }) { PgmIcon(PgmIcons.MY_LOCATION, "Pokaż mnie") }
            if (target != null) {
                SmallFloatingActionButton(onClick = { focusOn(target.latitude, target.longitude, 17.0) }) { PgmIcon(PgmIcons.FLAG, "Pokaż cel") }
            }
            SmallFloatingActionButton(onClick = { listOpen = true }) { PgmIcon(PgmIcons.LIST, "Lista punktów") }
        }

        // Emulator / gracz daleko od Płocka – podpowiedź z trybem demo
        if (farAway && !session.isCompleted) {
            Surface(
                modifier = Modifier.align(Alignment.CenterStart).padding(start = 12.dp, end = 72.dp),
                shape = RoundedCornerShape(12.dp),
                color = MaterialTheme.colorScheme.tertiaryContainer,
                shadowElevation = 4.dp,
            ) {
                Column(Modifier.padding(12.dp)) {
                    Text(
                        if (vm.demoAvailable) "Twoja pozycja jest daleko od Płocka (emulator domyślnie „stoi” w Kalifornii)."
                        else "Twoja pozycja jest daleko od Płocka – punkty zaliczysz dopiero na miejscu.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onTertiaryContainer,
                    )
                    if (vm.demoAvailable) TextButton(onClick = { vm.toggleDemoMode(true) }) { Text("Włącz tryb demo") }
                }
            }
        }

        // Dolny panel
        Column(Modifier.align(Alignment.BottomCenter).fillMaxWidth().navigationBarsPadding()) {
            SnackbarHost(snackbar)
            Card(
                modifier = Modifier.fillMaxWidth().padding(12.dp),
                shape = RoundedCornerShape(20.dp),
                elevation = CardDefaults.cardElevation(defaultElevation = 6.dp),
            ) {
                if (session.isCompleted) {
                    FinishedPanel(vm, game, session)
                } else if (target != null) {
                    WaypointPanel(
                        vm = vm,
                        waypoint = target,
                        session = session,
                        location = location,
                        distance = distance,
                        hasPermission = hasPermission || vm.demoMode,
                        onRequestPermission = {
                            permissionLauncher.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
                        },
                        onOpenTask = { taskOpen = true },
                        onNavigate = { vm.startRoute(location, target.latitude, target.longitude, target.name) },
                    )
                }
            }
        }
    }

    if (listOpen) {
        WaypointListDialog(
            game = game,
            session = session,
            currentId = target?.id,
            onSelect = { wp ->
                vm.selectedWaypointId = wp.id
                focusOn(wp.latitude, wp.longitude, 17.0)
                listOpen = false
            },
            onClose = { listOpen = false },
        )
    }

    val task = target?.task
    if (taskOpen && task != null && target != null) {
        TaskSheet(
            vm = vm,
            waypoint = target,
            task = task,
            submission = targetState?.task?.submission,
            onClose = { taskOpen = false },
        )
    }
}

@Composable
private fun WaypointPanel(
    vm: AppViewModel,
    waypoint: Waypoint,
    session: SessionState,
    location: PlayerLocation?,
    distance: Double?,
    hasPermission: Boolean,
    onRequestPermission: () -> Unit,
    onOpenTask: () -> Unit,
    onNavigate: () -> Unit,
) {
    val state = session.stateOf(waypoint.id)
    val taskState = state?.task
    val isCurrent = waypoint.id == session.nextWaypointId
    val nextWaypoint = session.nextWaypointId?.let { id -> vm.game?.waypoints?.firstOrNull { it.id == id } }
    var showHint by remember(waypoint.id) { mutableStateOf(false) }

    Column(
        Modifier.padding(16.dp).verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Tag("Punkt ${waypoint.position}", MaterialTheme.colorScheme.primary, MaterialTheme.colorScheme.onPrimary)
            Text(waypoint.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
        }

        if (!waypoint.description.isNullOrBlank()) {
            Text(waypoint.description, style = MaterialTheme.typography.bodyMedium, maxLines = 4)
        }

        // Odległość / stan dotarcia
        when {
            state?.completed == true && !isCurrent -> Row(verticalAlignment = Alignment.CenterVertically) {
                PgmIcon(PgmIcons.CHECK, null, Modifier.size(18.dp), tint = MaterialTheme.colorScheme.primary)
                Text(" Punkt ukończony", color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.SemiBold)
            }
            !isCurrent -> Text(
                if (nextWaypoint != null) "Punkty zaliczasz po kolei. Najpierw dotrzyj do punktu ${nextWaypoint.position}: „${nextWaypoint.name}”."
                else "Punkty zaliczasz po kolei.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            state?.reached == true -> Row(verticalAlignment = Alignment.CenterVertically) {
                PgmIcon(PgmIcons.CHECK, null, Modifier.size(18.dp), tint = MaterialTheme.colorScheme.primary)
                Text(" Jesteś na miejscu", color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.SemiBold)
            }
            !hasPermission -> Column {
                Text("Aplikacja potrzebuje dostępu do lokalizacji, żeby sprawdzić, czy dotarłeś do punktu.")
                TextButton(onClick = onRequestPermission) { Text("Zezwól na lokalizację") }
            }
            location == null -> Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
                Text(if (vm.demoMode) "Przytrzymaj palec na mapie, aby ustawić pozycję." else "Szukam sygnału GPS…")
            }
            location.accuracyM > MAX_ACCURACY_M -> Text(
                LOW_ACCURACY_MESSAGE + " (dokładność: ${location.accuracyM.toInt()} m)",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.tertiary,
            )
            distance != null -> Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Do celu: ${distanceLabel(distance)} (podejdź na ${waypoint.arrivalRadiusM} m)",
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.weight(1f),
                )
                val routed = vm.navRoute?.let { it.targetLat == waypoint.latitude && it.targetLng == waypoint.longitude } == true
                if (!routed) {
                    TextButton(onClick = onNavigate, enabled = !vm.routeLoading) {
                        PgmIcon(PgmIcons.WALK, null, Modifier.size(18.dp))
                        Text(" Prowadź")
                    }
                }
            }
        }

        if (!waypoint.playerHint.isNullOrBlank()) {
            if (showHint) {
                Text("💡 ${waypoint.playerHint}", style = MaterialTheme.typography.bodySmall)
            } else {
                TextButton(onClick = { showHint = true }) { Text("💡 Pokaż wskazówkę, jak znaleźć miejsce") }
            }
        }

        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            if (isCurrent && state?.reached != true && location != null) {
                OutlinedButton(
                    onClick = {
                        if (location.accuracyM > MAX_ACCURACY_M || !location.isFresh()) {
                            vm.message = LOW_ACCURACY_MESSAGE
                        } else {
                            vm.arrive(waypoint.id, location)
                        }
                    },
                    enabled = !vm.arriving,
                    modifier = Modifier.weight(1f),
                ) {
                    if (vm.arriving) CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp) else Text("Jestem na miejscu")
                }
            }
            val task = waypoint.task
            if (task != null && taskState != null && (isCurrent || taskState.submission != null)) {
                val submission = taskState.submission
                when {
                    submission != null -> FilledTonalButton(onClick = onOpenTask, modifier = Modifier.weight(1f)) {
                        Text("Wynik: ${submission.pointsAwarded}/${submission.maxPoints} pkt")
                    }
                    taskState.available -> Button(onClick = onOpenTask, enabled = !vm.busy, modifier = Modifier.weight(1f)) {
                        Text("Wykonaj zadanie")
                    }
                    else -> Text(
                        if (task.unlockMode == "after_previous") "Zadanie odblokuje się po ukończeniu poprzedniego punktu"
                        else "Zadanie odblokuje się, gdy dotrzesz na miejsce",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.weight(1f).padding(top = 10.dp),
                    )
                }
            }
        }

        if (vm.selectedWaypointId != null && vm.selectedWaypointId != session.nextWaypointId && session.nextWaypointId != null) {
            TextButton(onClick = { vm.selectedWaypointId = null }) { Text("Pokaż następny punkt trasy") }
        }
    }
}

@Composable
private fun FinishedPanel(vm: AppViewModel, game: GameDetail, session: SessionState) {
    Column(
        Modifier.padding(20.dp).fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Text("🎉 Gra ukończona!", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
        Text("Twój wynik", color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text("${session.score} / ${session.maxScore} pkt", style = MaterialTheme.typography.displaySmall, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
        Text(
            "W tym ${session.completionPoints} pkt za ukończenie trasy „${game.title}”.",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(Modifier.height(4.dp))
        Column(Modifier.fillMaxWidth().heightIn(max = 180.dp).verticalScroll(rememberScrollState())) {
            for (wp in game.waypoints) {
                val submission = session.stateOf(wp.id)?.task?.submission
                Row(Modifier.fillMaxWidth().padding(vertical = 3.dp), verticalAlignment = Alignment.CenterVertically) {
                    Text("${wp.position}. ${wp.name}", modifier = Modifier.weight(1f), maxLines = 1)
                    Text(
                        when {
                            submission == null -> "✓"
                            submission.isCorrect == false -> "✗ 0/${submission.maxPoints}"
                            else -> "${submission.pointsAwarded}/${submission.maxPoints} pkt"
                        },
                        fontWeight = FontWeight.SemiBold,
                        color = if (submission?.isCorrect == false) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.primary,
                    )
                }
            }
        }
        Button(onClick = { vm.back() }, modifier = Modifier.fillMaxWidth()) { Text("Zobacz ranking gry") }
    }
}

/** Lista wszystkich punktów trasy ze stanem – kliknięcie pokazuje punkt na mapie. */
@Composable
private fun WaypointListDialog(
    game: GameDetail,
    session: SessionState,
    currentId: Int?,
    onSelect: (Waypoint) -> Unit,
    onClose: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onClose,
        title = { Text("Punkty trasy") },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState())) {
                for (wp in game.waypoints) {
                    val state = session.stateOf(wp.id)
                    Row(
                        Modifier.fillMaxWidth().clickable { onSelect(wp) }.padding(vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        Text(
                            when {
                                state?.completed == true -> "✅"
                                wp.id == currentId -> "🎯"
                                state?.reached == true -> "📍"
                                else -> "⚪"
                            },
                        )
                        Column(Modifier.weight(1f)) {
                            Text("${wp.position}. ${wp.name}", fontWeight = if (wp.id == currentId) FontWeight.Bold else FontWeight.Normal)
                            wp.task?.let {
                                Text(taskTypeLabel(it.taskType), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                    }
                    HorizontalDivider()
                }
            }
        },
        confirmButton = { TextButton(onClick = onClose) { Text("Zamknij") } },
    )
}
