<template>
    <el-tooltip
        :content="tooltipContent"
        raw-content
        placement="top"
        :width="350"
    >
        <el-button
            type="primary"
            @click="next"
            size="small"
            style="
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                border: none;
                border-radius: 8px;
                padding: 8px 16px;
                font-weight: 500;
            "
        >
            <span style="margin-right: 6px">📋</span>
            Status Persetujuan
        </el-button>
    </el-tooltip>
</template>

<script lang="ts" setup>
import { ref, computed } from "vue";
import { ElTooltip } from "element-plus";
import "element-plus/dist/index.css";

const active = ref(0);

// Data dalam satu array seperti yang diminta
const approvalData = ref(["M.REZA: Y, RIDHO RAHMAT: T, BUDI SANTOSO: N"]);

// Parse data untuk mendapatkan informasi yang lebih terstruktur
const parsedData = computed(() => {
    const allApprovals = approvalData.value.join(", ").split(", ");
    console.log(allApprovals);
    return allApprovals.map((item) => {
        const [name, status] = item.split(": ");
        return { name: name.trim(), status: status.trim() };
    });
});

const tooltipContent = computed(() => {
    const totalApprovals = parsedData.value.length;
    const approvedCount = parsedData.value.filter(
        (item) => item.status === "Y"
    ).length;
    const rejectedCount = parsedData.value.filter(
        (item) => item.status === "T"
    ).length;
    const progressCount = parsedData.value.filter(
        (item) => item.status === "N"
    ).length;
    const approvalRate = Math.round((approvedCount / totalApprovals) * 100);

    // Design yang sesuai dengan tema aplikasi
    return `
    <div style="
      width: 340px;
      background: #ffffff;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 10px 40px rgba(99, 102, 241, 0.15);
      border: 1px solid rgba(99, 102, 241, 0.1);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    ">
      <!-- Header dengan gradient yang matching -->
      <div style="
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        padding: 12px 16px;
        border-radius: 12px;
        margin: -16px -16px 16px -16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
      ">
        <div style="
          font-weight: 600;
          font-size: 15px;
        ">Status Persetujuan Izin</div>
        <div style="
          background: rgba(255, 255, 255, 0.2);
          padding: 4px 10px;
          border-radius: 20px;
          font-size: 11px;
          font-weight: 500;
          backdrop-filter: blur(10px);
          white-space: nowrap;
        ">${approvalRate}% Disetujui</div>
      </div>

      <!-- Approval list dengan design yang sesuai tema -->
      <div style="max-height: 200px; overflow-y: auto; margin-bottom: 14px;">
        ${parsedData.value
            .map((item, index) => {
                const isActive = active.value === index;
                const isApproved = item.status === "Y";
                const isRejected = item.status === "T";
                const isProgress = item.status === "N";

                return `
            <div style="
              display: flex;
              align-items: center;
              padding: 12px;
              margin-bottom: 6px;
              background: ${
                  isActive
                      ? "linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%)"
                      : "transparent"
              };
              border: 1px solid ${isActive ? "#e2e8f0" : "transparent"};
              border-radius: 12px;
              transition: all 0.3s ease;
              cursor: pointer;
            ">
              <!-- Status indicator dengan warna yang sesuai -->
              <div style="
                width: 28px;
                height: 28px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 12px;
                font-size: 12px;
                font-weight: 600;
                background: ${
                    isApproved
                        ? "linear-gradient(135deg, #10b981 0%, #059669 100%)"
                        : isRejected
                        ? "linear-gradient(135deg, #ef4444 0%, #dc2626 100%)"
                        : isProgress
                        ? "linear-gradient(135deg, #f59e0b 0%, #d97706 100%)"
                        : "#f3f4f6"
                };
                color: ${
                    isApproved || isRejected || isProgress ? "white" : "#6b7280"
                };
                box-shadow: ${
                    isApproved
                        ? "0 2px 8px rgba(16, 185, 129, 0.3)"
                        : isRejected
                        ? "0 2px 8px rgba(239, 68, 68, 0.3)"
                        : isProgress
                        ? "0 2px 8px rgba(245, 158, 11, 0.3)"
                        : "none"
                };
                flex-shrink: 0;
              ">
                ${isApproved ? "✓" : isRejected ? "✕" : isProgress ? "⏳" : "?"}
              </div>

              <!-- Name and status -->
              <div style="flex: 1; min-width: 0;">
                <div style="
                  font-weight: 600;
                  font-size: 13px;
                  color: #1f2937;
                  margin-bottom: 3px;
                  overflow: hidden;
                  text-overflow: ellipsis;
                  white-space: nowrap;
                ">${item.name}</div>
                <div style="
                  font-size: 10px;
                  color: ${
                      isApproved
                          ? "#059669"
                          : isRejected
                          ? "#dc2626"
                          : isProgress
                          ? "#d97706"
                          : "#6b7280"
                  };
                  font-weight: 500;
                  padding: 2px 6px;
                  border-radius: 4px;
                  background: ${
                      isApproved
                          ? "rgba(16, 185, 129, 0.1)"
                          : isRejected
                          ? "rgba(239, 68, 68, 0.1)"
                          : isProgress
                          ? "rgba(245, 158, 11, 0.1)"
                          : "rgba(107, 114, 128, 0.1)"
                  };
                  display: inline-block;
                  text-transform: uppercase;
                  letter-spacing: 0.3px;
                ">
                  ${
                      isApproved
                          ? "DISETUJUI"
                          : isRejected
                          ? "DITOLAK"
                          : isProgress
                          ? "SEDANG DIPROSES"
                          : "PENDING"
                  }
                </div>
              </div>

              <!-- Active indicator -->
              ${
                  isActive
                      ? `
                <div style="
                  width: 6px;
                  height: 6px;
                  border-radius: 50%;
                  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                  margin-left: 8px;
                  animation: pulse 2s infinite;
                  flex-shrink: 0;
                "></div>
              `
                      : ""
              }
            </div>
          `;
            })
            .join("")}
      </div>

      <!-- Summary stats dengan warna yang sesuai -->
      <div style="
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
      ">
        <div style="
          text-align: center;
          padding: 8px 4px;
          background: rgba(16, 185, 129, 0.1);
          border-radius: 8px;
          border: 1px solid rgba(16, 185, 129, 0.2);
        ">
          <div style="
            font-size: 16px;
            font-weight: 700;
            color: #059669;
            margin-bottom: 1px;
          ">${approvedCount}</div>
          <div style="
            font-size: 9px;
            color: #059669;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
          ">Disetujui</div>
        </div>

        <div style="
          text-align: center;
          padding: 8px 4px;
          background: rgba(245, 158, 11, 0.1);
          border-radius: 8px;
          border: 1px solid rgba(245, 158, 11, 0.2);
        ">
          <div style="
            font-size: 16px;
            font-weight: 700;
            color: #d97706;
            margin-bottom: 1px;
          ">${progressCount}</div>
          <div style="
            font-size: 9px;
            color: #d97706;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
          ">Proses</div>
        </div>

        <div style="
          text-align: center;
          padding: 8px 4px;
          background: rgba(239, 68, 68, 0.1);
          border-radius: 8px;
          border: 1px solid rgba(239, 68, 68, 0.2);
        ">
          <div style="
            font-size: 16px;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 1px;
          ">${rejectedCount}</div>
          <div style="
            font-size: 9px;
            color: #dc2626;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
          ">Ditolak</div>
        </div>
      </div>
    </div>

    <style>
      @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.1); }
      }

      .el-tooltip {
  background-color: #fff !important;  /* Set your preferred background color */
  color: #000 !important;  /* Set your preferred text color */
}

.el-tooltip.is-dark {
  background-color: #ffffff !important;  /* Remove the dark background */
  color: #000 !important;  /* Ensure the text remains visible */
  border:none;
  border-radius:12px;

}
/* Remove the dark background from the arrow and make it light */
.el-popover_arrow.is-dark,
.el-popover_arrow.is-dark::before {
  background: var(--el-text-color-primary) !important; /* Replace with your preferred color */
  border: 1px solid var(--el-text-color-primary) !important; /* Optional: Remove the dark border */
}

/* For the arrow's before pseudo-element, ensure it uses the same color */
.el-popover_arrow::before {
  background: var(--el-text-color-primary) !important; /* Light color */
  border: 1px solid var(--el-text-color-primary) !important; /* Optional: Border styling */
}

/* Reset transform and height properties for the arrow */
.el-popover_arrow::before {
  transform: rotate(45deg); /* Keeps the original shape */
  height: 10px; /* You can adjust this value as needed */
}

    </style>
  `;
});

const next = () => {
    active.value = (active.value + 1) % parsedData.value.length;
};
</script>
