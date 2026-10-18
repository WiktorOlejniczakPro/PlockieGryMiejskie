package pl.plock.pgm.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import pl.plock.pgm.data.RouteMode
import pl.plock.pgm.location.PlayerLocation

/** Pasek wyznaczonej trasy: długość, czas, pieszo/rowerem i „Zakończ”. */
@Composable
fun RouteBar(vm: AppViewModel, location: PlayerLocation?, modifier: Modifier = Modifier) {
    val route = vm.navRoute
    if (route == null && !vm.routeLoading) return
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = MaterialTheme.colorScheme.secondaryContainer,
        shadowElevation = 4.dp,
    ) {
        Column(Modifier.padding(start = 12.dp, top = 6.dp, bottom = 6.dp, end = 4.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                PgmIcon(if (vm.routeMode == RouteMode.BIKE) PgmIcons.BIKE else PgmIcons.WALK, null, Modifier.size(20.dp))
                Column(Modifier.weight(1f).padding(start = 8.dp)) {
                    if (route != null) {
                        Text(
                            "${distanceLabel(route.distanceM)} · ok. ${durationLabel(route.durationS)}",
                            fontWeight = FontWeight.Bold,
                        )
                        Text(
                            if (route.approximate) "w linii prostej do: ${route.targetName} (szukam trasy po drogach…)"
                            else "do: ${route.targetName}",
                            style = MaterialTheme.typography.labelSmall,
                            maxLines = 2,
                        )
                    } else {
                        Text("Wyznaczam trasę…", fontWeight = FontWeight.SemiBold)
                    }
                }
                if (vm.routeLoading) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
                IconButton(onClick = { vm.stopRoute(byUser = true) }) { PgmIcon(PgmIcons.CLOSE, "Zakończ prowadzenie", Modifier.size(20.dp)) }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                for (mode in RouteMode.entries) {
                    FilterChip(
                        selected = vm.routeMode == mode,
                        onClick = { if (vm.routeMode != mode) vm.changeRouteMode(mode, location) },
                        label = { Text(mode.label) },
                        leadingIcon = { PgmIcon(if (mode == RouteMode.BIKE) PgmIcons.BIKE else PgmIcons.WALK, null, Modifier.size(16.dp)) },
                    )
                }
            }
        }
    }
}

/** „8 min”, „1 godz. 5 min”. */
fun durationLabel(seconds: Double): String {
    val min = Math.max(1, Math.round(seconds / 60.0).toInt())
    return if (min < 60) "$min min" else "${min / 60} godz. ${min % 60} min"
}
