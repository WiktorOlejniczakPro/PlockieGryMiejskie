package pl.plock.pgm.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pl.plock.pgm.data.Redemption
import kotlinx.coroutines.delay
import androidx.compose.material3.IconButton
import pl.plock.pgm.data.Reward

/**
 * Sklep punktów: przypięte saldo na górze, nagrody z panelu administratora
 * i historia wymian z kodami. Saldo i wymiany liczy wyłącznie serwer.
 */
@Composable
fun ShopScreen(vm: AppViewModel, modifier: Modifier = Modifier) {
    // nagrody dodane lub zmienione w panelu pojawiają się bez wychodzenia ze sklepu
    LaunchedEffect(Unit) {
        vm.loadShop()
        while (true) {
            delay(20_000)
            vm.loadShop(silent = true)
        }
    }
    var tab by remember { mutableIntStateOf(0) }
    var toRedeem by remember { mutableStateOf<Reward?>(null) }

    Column(modifier.fillMaxSize()) {
        // Pasek salda – zawsze widoczny (nie przewija się z listą)
        Surface(color = MaterialTheme.colorScheme.primary, contentColor = MaterialTheme.colorScheme.onPrimary) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                PgmIcon(PgmIcons.TROPHY, null, Modifier.size(28.dp))
                Column(Modifier.weight(1f).padding(start = 12.dp)) {
                    Text("Twoje punkty", style = MaterialTheme.typography.labelMedium)
                    Text(
                        vm.balance?.balance?.toString() ?: "…",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                    )
                }
                vm.balance?.let {
                    Column(horizontalAlignment = Alignment.End) {
                        Text("zdobyte: ${it.earned}", style = MaterialTheme.typography.labelSmall)
                        Text("wydane: ${it.spent}", style = MaterialTheme.typography.labelSmall)
                    }
                }
                IconButton(onClick = { vm.loadShop() }, enabled = !vm.shopLoading) {
                    PgmIcon(PgmIcons.REFRESH, "Odśwież nagrody", tint = MaterialTheme.colorScheme.onPrimary)
                }
            }
        }

        TabRow(selectedTabIndex = tab) {
            Tab(selected = tab == 0, onClick = { tab = 0 }, text = { Text("Nagrody") })
            Tab(selected = tab == 1, onClick = { tab = 1 }, text = { Text("Moje kupony (${vm.redemptions.size})") })
        }
        if (vm.shopLoading) LinearProgressIndicator(Modifier.fillMaxWidth())

        when {
            vm.shopError != null && vm.rewards.isEmpty() -> ErrorBox(vm.shopError!!, onRetry = { vm.loadShop() })
            tab == 0 -> RewardList(vm.rewards, onRedeem = { toRedeem = it })
            else -> RedemptionList(vm.redemptions)
        }
    }

    toRedeem?.let { reward ->
        // klucz tworzony raz dla tego okna – ponowienie po błędzie sieci = ta sama wymiana
        val key = remember(reward.id) { vm.newIdempotencyKey() }
        val balance = vm.balance?.balance ?: 0
        AlertDialog(
            onDismissRequest = { if (!vm.busy) toRedeem = null },
            title = { Text("Wymienić punkty?") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(reward.title, fontWeight = FontWeight.Bold)
                    Text(
                        listOfNotNull(reward.valueLabel, reward.partnerName).joinToString(" · "),
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.primary,
                    )
                    reward.description?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    reward.terms?.let { Text("Warunki: $it", style = MaterialTheme.typography.bodySmall) }
                    Text("Koszt: ${reward.costPoints} pkt")
                    Text("Twoje saldo: $balance pkt → po wymianie: ${balance - reward.costPoints} pkt")
                    if (reward.isSample) {
                        Text(
                            "To nagroda przykładowa – kod nie ma wartości handlowej.",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.tertiary,
                        )
                    }
                }
            },
            confirmButton = {
                Button(onClick = { vm.redeem(reward, key) { ok -> if (ok) toRedeem = null } }, enabled = !vm.busy) {
                    if (vm.busy) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp) else Text("Wymień")
                }
            },
            dismissButton = { TextButton(onClick = { toRedeem = null }, enabled = !vm.busy) { Text("Anuluj") } },
        )
    }

    vm.lastRedemption?.let { r ->
        AlertDialog(
            onDismissRequest = { vm.lastRedemption = null },
            title = { Text("Gotowe!") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("Wymieniono ${r.costPoints} pkt na: ${r.title}")
                    Text(
                        if (r.rewardType == "gift_card") "Numer Twojej karty podarunkowej:" else "Twój kod rabatowy:",
                        style = MaterialTheme.typography.labelMedium,
                    )
                    r.partnerName?.let { Text("Do wykorzystania w: $it", style = MaterialTheme.typography.bodySmall) }
                    CodeBox(r.code)
                    r.redemptionInstructions?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    Text("Kod znajdziesz zawsze w zakładce „Moje kupony”.", style = MaterialTheme.typography.bodySmall)
                }
            },
            confirmButton = { Button(onClick = { vm.lastRedemption = null }) { Text("OK") } },
        )
    }
}

@Composable
private fun RewardList(rewards: List<Reward>, onRedeem: (Reward) -> Unit) {
    if (rewards.isEmpty()) {
        Column(Modifier.fillMaxSize().padding(32.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
            Text("Sklep jest jeszcze pusty", style = MaterialTheme.typography.titleMedium)
            Text(
                "Nagrody dodaje organizator w panelu (Kreator nagród). Lista odświeża się sama.",
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            )
        }
        return
    }
    LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        items(rewards, key = { it.id }) { reward ->
            Card(
                Modifier.fillMaxWidth(),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
            ) {
                Row(Modifier.padding(16.dp), horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                    Surface(shape = RoundedCornerShape(12.dp), color = MaterialTheme.colorScheme.primaryContainer) {
                        Column(Modifier.padding(10.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            PgmIcon(
                                rewardIcon(reward.rewardType), null,
                                Modifier.size(26.dp),
                                tint = MaterialTheme.colorScheme.onPrimaryContainer,
                            )
                            reward.valueLabel?.let {
                                Text(it, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onPrimaryContainer)
                            }
                        }
                    }
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Text(reward.title, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f, fill = false))
                            if (reward.isSample) {
                                Tag("PRZYKŁAD", MaterialTheme.colorScheme.tertiaryContainer, MaterialTheme.colorScheme.onTertiaryContainer)
                            }
                        }
                        Text(
                            rewardTypeLabel(reward.rewardType) + (reward.partnerName?.let { " · $it" } ?: ""),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.primary,
                        )
                        reward.description?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                        reward.minOrderAmount?.let {
                            Text("Przy zakupach od ${moneyLabel(it)} zł", style = MaterialTheme.typography.bodySmall)
                        }
                        Text(
                            buildString {
                                append(if (reward.stockLeft == null) "Bez limitu" else "Zostało kodów: ${reward.stockLeft}")
                                val limit = reward.perPlayerLimit
                                if (limit != null) append(if (limit == 1) " · 1 na gracza" else " · do $limit na gracza")
                                reward.validUntil?.let { append(" · ważna do ${dateLabel(it).substringBefore(',')}") }
                            },
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(top = 4.dp)) {
                            Text("${reward.costPoints} pkt", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.weight(1f))
                            Button(onClick = { onRedeem(reward) }, enabled = reward.available && reward.affordable) {
                                Text(
                                    when {
                                        reward.playerLimitLeft == 0 -> "Już odebrana"
                                        !reward.available -> "Brak kodów"
                                        !reward.affordable -> "Za mało punktów"
                                        else -> "Wymień punkty"
                                    },
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun RedemptionList(redemptions: List<Redemption>) {
    if (redemptions.isEmpty()) {
        Column(Modifier.fillMaxSize().padding(32.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
            Text("Nie masz jeszcze kuponów", style = MaterialTheme.typography.titleMedium)
            Text("Zdobywaj punkty w grach i wymieniaj je na nagrody.", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        return
    }
    LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        items(redemptions, key = { it.id }) { r ->
            Card(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(r.title, fontWeight = FontWeight.Bold)
                            Text(
                                listOfNotNull(r.valueLabel, r.partnerName).joinToString(" · "),
                                style = MaterialTheme.typography.labelMedium,
                                color = MaterialTheme.colorScheme.primary,
                            )
                        }
                        Tag(
                            when (r.status) { "used" -> "Wykorzystany"; "cancelled" -> "Anulowany"; else -> "Aktywny" },
                            if (r.status == "issued") MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceVariant,
                            if (r.status == "issued") MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    CodeBox(r.code)
                    Text("${r.costPoints} pkt · ${dateLabel(r.createdAt)}", style = MaterialTheme.typography.labelSmall)
                    r.redemptionInstructions?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    if (r.isSample) {
                        Text("Nagroda przykładowa – bez wartości handlowej.", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.tertiary)
                    }
                }
            }
        }
    }
}

@Composable
private fun CodeBox(code: String) {
    Surface(shape = RoundedCornerShape(10.dp), color = MaterialTheme.colorScheme.surfaceVariant) {
        Text(
            code,
            fontFamily = FontFamily.Monospace,
            fontWeight = FontWeight.Bold,
            fontSize = 22.sp,
            letterSpacing = 2.sp,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
        )
    }
}

private fun rewardIcon(type: String): String = when (type) {
    "discount_code" -> PgmIcons.TAG
    "gift_card" -> PgmIcons.GIFT
    else -> PgmIcons.TROPHY
}

private fun rewardTypeLabel(type: String): String = when (type) {
    "discount_code" -> "Kod rabatowy"
    "gift_card" -> "Karta podarunkowa"
    else -> "Nagroda"
}

/** 100.0 -> "100", 12.5 -> "12,50". */
private fun moneyLabel(value: Double): String =
    if (value % 1.0 == 0.0) value.toLong().toString() else "%.2f".format(java.util.Locale("pl", "PL"), value)
