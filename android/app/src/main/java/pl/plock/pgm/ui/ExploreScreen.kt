package pl.plock.pgm.ui

import android.Manifest
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SmallFloatingActionButton
import androidx.compose.material3.Surface
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
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import pl.plock.pgm.Config
import pl.plock.pgm.data.Place
import pl.plock.pgm.location.PlayerLocation

/**
 * „Odkrywaj Płock” – ciekawe miejsca z otwartych danych miasta (zabytki, parki,
 * rower miejski, place zabaw…) na mapie i na liście od najbliższych.
 * Pozwala zaplanować spacer także poza grą i pokazuje wykorzystanie danych miejskich.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ExploreScreen(vm: AppViewModel, modifier: Modifier = Modifier) {
    val tracker = vm.locationTracker
    var hasPermission by remember { mutableStateOf(tracker.hasPermission()) }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { result ->
        hasPermission = result.values.any { it }
    }
    DisposableEffect(hasPermission) {
        if (hasPermission) tracker.start()
        onDispose { tracker.stop() }
    }
    val realLocation by tracker.location.collectAsState()
    val anyLocation = vm.effectiveLocation(realLocation)
    // pozycja daleko od Płocka (np. emulator w Kalifornii) nie nadaje się do szukania miejsc
    val location = anyLocation?.takeIf { it.isNearPlock() }
    val outsidePlock = anyLocation != null && location == null

    var focusKey by remember { mutableIntStateOf(0) }
    var focus by remember { mutableStateOf<MapFocus?>(null) }
    fun focusOn(lat: Double, lng: Double, zoom: Double? = null) {
        focusKey++
        focus = MapFocus(focusKey, lat, lng, zoom)
    }

    // Pierwsze wejście: miejsca wokół gracza (jeśli jest w Płocku) albo wokół centrum
    var initialized by remember { mutableStateOf(false) }
    LaunchedEffect(location != null) {
        if (!initialized || (location != null && vm.exploreCenter == null)) {
            initialized = true
            vm.loadPlaces(location)
            val center = vm.exploreCenter
            when {
                center != null -> focusOn(center.first, center.second, 15.0)
                location != null -> focusOn(location.latitude, location.longitude, 16.0)
                else -> focusOn(Config.PLOCK_LAT, Config.PLOCK_LNG, 15.0)
            }
        }
    }

    // nowa trasa – widok obejmuje gracza i cel; zejście z trasy – nowa trasa
    LaunchedEffect(vm.routeVersion) {
        val r = vm.navRoute ?: return@LaunchedEffect
        focusKey++
        focus = MapFocus(focusKey, r.targetLat, r.targetLng, fit = r.points)
    }
    LaunchedEffect(location) { vm.followRoute(location) }

    val selected = vm.selectedPlace
    var showInfo by remember { mutableStateOf(true) }

    Column(modifier.fillMaxSize()) {
        if (showInfo) {
            Surface(color = MaterialTheme.colorScheme.primaryContainer) {
                Row(Modifier.fillMaxWidth().padding(start = 16.dp, top = 8.dp, bottom = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        "Ciekawe miejsca Płocka z otwartych danych miasta – zaplanuj spacer, znajdź zabytek, " +
                            "rower miejski albo plac zabaw. Dotknij punktu, aby zobaczyć szczegóły.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                        modifier = Modifier.weight(1f),
                    )
                    IconButton(onClick = { showInfo = false }) { PgmIcon(PgmIcons.CLOSE, "Ukryj opis", Modifier.size(18.dp)) }
                }
            }
        }

        LazyRow(
            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            items(PLACE_GROUPS, key = { it.label }) { group ->
                FilterChip(
                    selected = vm.exploreGroup == group,
                    onClick = { vm.selectPlaceGroup(group, location) },
                    label = { Text(group.label) },
                    leadingIcon = { PgmIcon(group.icon, null, Modifier.size(18.dp)) },
                )
            }
        }

        Box(Modifier.fillMaxWidth().weight(1f)) {
            OsmMap(
                points = vm.places.map { place ->
                    MapPoint(
                        id = place.id,
                        number = 0,
                        latitude = place.latitude,
                        longitude = place.longitude,
                        state = if (place.id == selected?.id) PointState.PLACE_SELECTED else PointState.PLACE,
                        icon = placeIcon(place.category),
                    )
                },
                player = anyLocation,
                focus = focus,
                drawRoute = false,
                fitToPoints = false,
                navRoute = vm.navRoute?.points,
                onPointClick = { id -> vm.selectedPlace = vm.places.firstOrNull { it.id == id } },
                onLongPress = { lat, lng ->
                    if (vm.demoMode) vm.simulatedLocation = PlayerLocation(lat, lng, 5f, simulated = true)
                    vm.exploreAt(lat, lng)
                },
                modifier = Modifier.fillMaxSize(),
            )

            Column(
                Modifier.align(Alignment.TopEnd).padding(10.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                SmallFloatingActionButton(onClick = {
                    when {
                        location != null -> {
                            vm.exploreAroundMe(location)
                            focusOn(location.latitude, location.longitude, 16.0)
                        }
                        outsidePlock -> vm.message = "Jesteś poza Płockiem – pokazuję centrum miasta. " +
                            "Przytrzymaj palec na mapie, aby szukać w innym miejscu."
                        !hasPermission -> permissionLauncher.launch(
                            arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION),
                        )
                        else -> vm.message = "Szukam sygnału GPS…"
                    }
                }) { PgmIcon(PgmIcons.MY_LOCATION, "Szukaj wokół mnie") }
                SmallFloatingActionButton(onClick = {
                    vm.exploreAt(Config.PLOCK_LAT, Config.PLOCK_LNG)
                    focusOn(Config.PLOCK_LAT, Config.PLOCK_LNG, 15.0)
                }) { PgmIcon(PgmIcons.CITY, "Centrum Płocka") }
            }

            RouteBar(vm, location, Modifier.align(Alignment.TopStart).padding(start = 10.dp, top = 10.dp, end = 64.dp))

            if (vm.placesLoading) {
                LinearProgressIndicator(Modifier.fillMaxWidth().align(Alignment.TopCenter))
            }

            if (outsidePlock && selected == null) {
                Surface(
                    modifier = Modifier.align(Alignment.BottomCenter).padding(10.dp),
                    shape = RoundedCornerShape(12.dp),
                    color = MaterialTheme.colorScheme.tertiaryContainer,
                ) {
                    Text(
                        "Jesteś daleko od Płocka, więc pokazuję centrum miasta. " +
                            "Przytrzymaj palec na mapie, aby szukać w innym miejscu.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onTertiaryContainer,
                        modifier = Modifier.padding(10.dp),
                    )
                }
            }

            if (selected != null) {
                PlaceCard(
                    place = selected,
                    onNavigate = {
                        if (location == null && outsidePlock) {
                            vm.message = "Jesteś daleko od Płocka – trasę wyznaczę, gdy będziesz w mieście."
                        } else {
                            vm.startRoute(location, selected.latitude, selected.longitude, selected.name)
                            vm.selectedPlace = null
                        }
                    },
                    onClose = { vm.selectedPlace = null },
                    modifier = Modifier.align(Alignment.BottomCenter).padding(10.dp),
                )
            }
        }

        Row(
            Modifier.fillMaxWidth().padding(start = 12.dp, end = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                when {
                    vm.placesLoading -> "Szukam…"
                    vm.places.isEmpty() -> "Brak miejsc w tym promieniu – zwiększ zasięg"
                    else -> "${vm.places.size} miejsc do ${distanceLabel(vm.exploreRadiusM.toDouble())} " +
                        if (vm.exploreCenter != null) "od wskazanego punktu" else if (location != null) "od Ciebie" else "od centrum"
                },
                style = MaterialTheme.typography.labelMedium,
                modifier = Modifier.weight(1f),
            )
            for (radius in listOf(500, 1000, 3000)) {
                TextButton(onClick = { vm.selectExploreRadius(radius, location) }) {
                    Text(
                        distanceLabel(radius.toDouble()),
                        fontWeight = if (vm.exploreRadiusM == radius) FontWeight.Bold else FontWeight.Normal,
                    )
                }
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxWidth().weight(0.8f),
            contentPadding = PaddingValues(bottom = 8.dp),
        ) {
            items(vm.places, key = { it.id }) { place ->
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clickable {
                            vm.selectedPlace = place
                            focusOn(place.latitude, place.longitude)
                        }
                        .padding(horizontal = 16.dp, vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    PgmIcon(placeIcon(place.category), null, Modifier.size(22.dp), tint = MaterialTheme.colorScheme.primary)
                    Column(Modifier.weight(1f)) {
                        Text(place.name, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        Text(
                            place.categoryName,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            maxLines = 1,
                        )
                    }
                    Text(distanceLabel(place.distanceM.toDouble()), style = MaterialTheme.typography.labelMedium)
                }
                HorizontalDivider()
            }
        }
    }
}

@Composable
private fun PlaceCard(place: Place, onNavigate: () -> Unit, onClose: () -> Unit, modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 6.dp),
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                PgmIcon(placeIcon(place.category), null, Modifier.size(18.dp), tint = MaterialTheme.colorScheme.primary)
                Text(
                    place.categoryName,
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.weight(1f),
                )
                IconButton(onClick = onClose) { PgmIcon(PgmIcons.CLOSE, "Zamknij", Modifier.size(20.dp)) }
            }
            Text(place.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            place.address?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
            place.description?.takeIf { it.isNotBlank() }?.let {
                Text(it, style = MaterialTheme.typography.bodySmall, maxLines = 4, overflow = TextOverflow.Ellipsis)
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("ok. ${distanceLabel(place.distanceM.toDouble())} stąd", style = MaterialTheme.typography.labelMedium, modifier = Modifier.weight(1f))
                FilledTonalButton(onClick = onNavigate) {
                    PgmIcon(PgmIcons.WALK, null, Modifier.size(18.dp))
                    Text(" Prowadź")
                }
            }
        }
    }
}
