function draw(xOffset, yOffset, xScale, yScale) {
    const canvas = document.getElementById('dragonfly');
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    let xCoord = window.dragonflypoints[0].x;
    let yCoord = window.dragonflypoints[0].y;

    // Draw points on the canvas
    for (idx = 1; idx < window.dragonflypoints.length; idx++) {
        let coord = window.dragonflypoints[idx];
        if (coord.x !== undefined) {
            xCoord += coord.x;
        }
        if (coord.y !== undefined) {
            yCoord += coord.y;
        }
        let x = xOffset + (width / 2) * (1 + (xScale * xCoord));
        let y = yOffset + (height / 2) * (1 + (yScale * yCoord));
        ctx.beginPath();
        ctx.arc(x, y, 1, 0, Math.PI); // Assuming a small radius of 2 for the points
        ctx.fillStyle = 'white'; // Color of the points
        ctx.fill();
        ctx.closePath();
    }
}

window.onload = function() {
    draw(-10, 0, 1.2, 0.75);
};
